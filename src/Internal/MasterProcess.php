<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Console\StdoutHandler;
use Luzrain\PHPStreamServer\Exception\AlreadyRunningException;
use Luzrain\PHPStreamServer\Exception\NotRunningException;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageHandler;
use Luzrain\PHPStreamServer\Internal\Scheduler\Scheduler;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\Supervisor\Supervisor;
use Luzrain\PHPStreamServer\PeriodicProcessInterface;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ProcessInterface;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcessInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;
use function Amp\Future\await;

/**
 * @internal
 */
final class MasterProcess
{
    private const GC_PERIOD = 300;

    private static bool $registered = false;
    private string $startFile;
    private string $pidFile;
    private string $socketFile;
    private Suspension $suspension;
    private Status $status = Status::STARTING;
    private ServerStatus $serverStatus;
    private MessageHandler $messageHandler;
    private Supervisor $supervisor;
    private Scheduler $scheduler;

    /**
     * @var array<class-string<Plugin>, Plugin>
     */
    private array $plugins = [];

    public function __construct(
        string|null $pidFile,
        int $stopTimeout,
        private readonly LoggerInterface $logger,
    ) {
        if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
            throw new \RuntimeException('Works in command line mode only');
        }

        if (self::$registered) {
            throw new \RuntimeException('Only one instance of server can be instantiated');
        }

        self::$registered = true;

        StdoutHandler::register();
        ErrorHandler::register($this->logger);

        $this->startFile = Functions::getStartFile();

        $runDirectory = $pidFile === null
            ? (\posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir())
            : \pathinfo($pidFile, PATHINFO_DIRNAME)
        ;

        $this->pidFile = $pidFile ?? \sprintf('%s/phpss%s.pid', $runDirectory, \hash('xxh32', $this->startFile));
        $this->socketFile = \sprintf('%s/phpss%s.socket', $runDirectory, \hash('xxh32', $this->startFile . 'rx'));

        $this->supervisor = new Supervisor($stopTimeout);
        $this->scheduler = new Scheduler();
        $this->serverStatus = new ServerStatus();
    }

    public function addWorker(WorkerProcessInterface|PeriodicProcessInterface ...$workers): void
    {
        foreach ($workers as $worker) {
            if ($worker instanceof WorkerProcessInterface) {
                $this->supervisor->addWorker($worker);
            } elseif($worker instanceof PeriodicProcessInterface) {
                $this->scheduler->addWorker($worker);
            }
            $this->serverStatus->addWorker($worker);
        }
    }

    public function addPlugin(Plugin ...$plugins): void
    {
        foreach ($plugins as $plugin) {
            $plugin->init($this);
            $this->plugins[] = $plugin;
        }
    }

    public function run(bool $daemonize = false): int
    {
        if ($this->isRunning()) {
            throw new AlreadyRunningException();
        }

        if ($daemonize && $this->doDaemonize()) {
            // Runs in caller process
            return 0;
        } elseif ($daemonize) {
            // Runs in daemonized master process
            StdoutHandler::disableStdout();
        }

        $this->saveMasterPid();
        $this->start();
        $this->status = Status::RUNNING;
        $this->supervisor->start($this->logger, $this->suspension, $this->getStatus(...), $this->serverStatus);
        $this->scheduler->start($this->logger, $this->suspension, $this->getStatus(...));
        $this->logger->info(Server::NAME . ' has started');

        $ret = $this->suspension->suspend();

        if ($ret instanceof ProcessInterface) {
            $workerProcess = $ret;
            $this->free();
            exit($workerProcess->run(new WorkerContext(
                socketFile: $this->socketFile,
                logger: $this->logger,
            )));
        }

        \assert(\is_int($ret));
        $this->onMasterShutdown();

        return $ret;
    }

    /**
     * Runs in master process
     */
    private function start(): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: master process  start_file=%s', Server::NAME, $this->startFile));
        }

        // Init event loop.
        EventLoop::setDriver(new StreamSelectDriver());
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = EventLoop::getDriver()->getSuspension();

        $this->messageHandler = new SocketFileMessageHandler($this->socketFile);

        $this->serverStatus->setRunning();
        $this->serverStatus->subscribeToWorkerMessages($this->messageHandler);

        $this->messageHandler->subscribe(ServerStatusRequest::class, $this->getServerStatus(...));

        $stopCallback = function (): void { $this->stop(); };
        $reloadCallback = function (): void { $this->reload(); };
        EventLoop::onSignal(SIGINT, $stopCallback);
        EventLoop::onSignal(SIGTERM, $stopCallback);
        EventLoop::onSignal(SIGHUP, $stopCallback);
        EventLoop::onSignal(SIGTSTP, $stopCallback);
        EventLoop::onSignal(SIGQUIT, $stopCallback);
        EventLoop::onSignal(SIGUSR1, $reloadCallback);

        // Force run garbage collection periodically
        EventLoop::repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });

        foreach ($this->plugins as $module) {
            $module->start();
        }
    }

    /**
     * Fork process for Daemonize
     *
     * @return bool return true in master process and false in child
     */
    private function doDaemonize(): bool
    {
        $pid = \pcntl_fork();
        if ($pid === -1) {
            throw new PHPStreamServerException('Fork fail');
        }
        if ($pid > 0) {
            return true;
        }
        if (\posix_setsid() === -1) {
            throw new PHPStreamServerException('Setsid fail');
        }
        return false;
    }

    private function saveMasterPid(): void
    {
        if (!\is_dir($pidFileDir = \dirname($this->pidFile))) {
            \mkdir(directory: $pidFileDir, recursive: true);
        }

        if (\file_exists($this->socketFile)) {
            \unlink($this->socketFile);
        }

        if (false === \file_put_contents($this->pidFile, (string) \posix_getpid())) {
            throw new PHPStreamServerException(\sprintf('Can\'t save pid to %s', $this->pidFile));
        }
    }

    private function onMasterShutdown(): void
    {
        if (\file_exists($this->pidFile)) {
            \unlink($this->pidFile);
        }

        if (\file_exists($this->socketFile)) {
            \unlink($this->socketFile);
        }
    }

    public function stop(int $code = 0): void
    {
        if (!$this->isRunning()) {
            throw new NotRunningException();
        }

        // If it called from outside working process
        if (($masterPid = $this->getPid()) !== \posix_getpid()) {
            echo Server::NAME ." stopping ...\n";
            \posix_kill($masterPid, SIGTERM);
            return;
        }

        if ($this->status === Status::SHUTDOWN) {
            return;
        }

        $this->status = Status::SHUTDOWN;
        $this->logger->info(Server::NAME . ' stopping ...');

        $stopFutures = [];
        $stopFutures[] = $this->supervisor->stop();
        $stopFutures[] = $this->scheduler->stop();
        foreach ($this->plugins as $plugin) {
            $stopFutures[] = $plugin->stop();
        }

        await($stopFutures);

        $this->logger->info(Server::NAME . ' stopped');
        $this->suspension->resume($code);
    }

    public function reload(): void
    {
        if (!$this->isRunning()) {
            throw new NotRunningException();
        }

        // If it called from outside working process
        if (($masterPid = $this->getPid()) !== \posix_getpid()) {
            echo Server::NAME . " reloading ...\n";
            \posix_kill($masterPid, SIGUSR1);
            return;
        }

        $this->logger->info(Server::NAME . ' reloading ...');
        $this->supervisor->reload();
    }

    private function getPid(): int
    {
        return \is_file($this->pidFile) ? (int) \file_get_contents($this->pidFile) : 0;
    }

    private function isRunning(): bool
    {
        if ($this->status === Status::RUNNING) {
            return true;
        }

        return $this->getPid() !== 0 && \posix_kill($this->getPid(), 0);
    }

    private function getStatus(): Status
    {
        return $this->status;
    }

    private function free(): void
    {
        unset($this->plugins);
        unset($this->serverStatus);
        unset($this->messageHandler);
        unset($this->supervisor);
        unset($this->scheduler);

        SIGCHLDHandler::unregister();

        EventLoop::queue(static function() {
            $identifiers = EventLoop::getDriver()->getIdentifiers();
            \array_walk($identifiers, EventLoop::getDriver()->cancel(...));
            EventLoop::getDriver()->stop();
        });
        EventLoop::getDriver()->run();

        \gc_collect_cycles();
        \gc_mem_caches();
    }

    public function getServerStatus(): ServerStatus
    {
        if ($this->status === Status::RUNNING || !$this->isRunning()) {
            return $this->serverStatus;
        }

        $bus = new SocketFileMessageBus($this->socketFile);
        $result = $bus->dispatch(new ServerStatusRequest());

        /** @var ServerStatus */
        return $result->await();
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getMessageHandler(): MessageHandler
    {
        return $this->messageHandler;
    }
}
