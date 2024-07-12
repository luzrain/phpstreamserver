<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Console\StdoutHandler;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\Relay\Relay;
use Luzrain\PHPStreamServer\Internal\Scheduler\Scheduler;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Connection;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connections;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\Supervisor\Supervisor;
use Luzrain\PHPStreamServer\Plugin\Module;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcess;
use PHPUnit\Runner\ErrorException;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;
use function Amp\Future\awaitAll;

/**
 * @internal
 */
final class MasterProcess
{
    private const GC_PERIOD = 300;

    private static bool $registered = false;
    private string $startFile;
    private string $pidFile;
    private string $rxPipeFile;
    private string $txPipeFile;
    private Suspension $suspension;
    private Status $status = Status::STARTING;
    private ServerStatus $serverStatus;
    private Relay $serverStatusRelay;
    private Supervisor $supervisor;
    private Scheduler $scheduler;

    /**
     * @var array<class-string<Module>, Module>
     */
    private array $modules = [];

    public function __construct(
        string|null $pidFile,
        private int $stopTimeout,
        public readonly LoggerInterface $logger,
    ) {
        if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
            throw new \RuntimeException('Works in command line mode only');
        }

        if (self::$registered) {
            throw new \RuntimeException('Only one instance of server can be instantiated');
        }

        StdoutHandler::register();
        ErrorHandler::register($this->logger);

        self::$registered = true;
        $this->startFile = Functions::getStartFile();

        $runDirectory = $pidFile === null
            ? (\posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir())
            : \pathinfo($pidFile, PATHINFO_DIRNAME)
        ;
        $this->pidFile = $pidFile ?? \sprintf('%s/phpss%s.pid', $runDirectory, \hash('xxh32', $this->startFile));
        $this->rxPipeFile = \sprintf('%s/phpss%s.pipe', $runDirectory, \hash('xxh32', $this->startFile . 'rx'));
        $this->txPipeFile = \sprintf('%s/phpss%s.pipe', $runDirectory, \hash('xxh32', $this->startFile . 'tx'));

        $this->serverStatus = new ServerStatus();
        $this->supervisor = new Supervisor($this->stopTimeout);
        $this->scheduler = new Scheduler();
    }

    public function addWorkers(WorkerProcess ...$workers): void
    {
        foreach ($workers as $worker) {
            $this->supervisor->addWorker($worker);
            $this->serverStatus->addWorker($worker);
        }
    }

    public function addPeriodicTasks(PeriodicProcess ...$workers): void
    {
        foreach ($workers as $worker) {
            $this->scheduler->addWorker($worker);
            $this->serverStatus->addPeriodicTask($worker);
        }
    }

    public function addModules(Module ...$modules): void
    {
        foreach ($modules as $module) {
            if (isset($this->modules[$module::class])) {
                throw new ErrorException('Can not load more than one instance of module');
            }

            $this->modules[$module::class] = $module;
        }
    }

    public function run(bool $daemonize = false): int
    {
        if ($this->isRunning()) {
            $this->logger->error('Master process already running');
            return 1;
        }

        if ($daemonize && $this->doDaemonize()) {
            // Runs in caller process
            return 0;
        } elseif ($daemonize) {
            // Runs in daemonized master process
            StdoutHandler::disableStdout();
        }

        $this->saveMasterPid();
        $this->initServer();
        $this->status = Status::RUNNING;
        $this->supervisor->start($this, $this->suspension);
        $this->scheduler->start($this, $this->suspension);
        $this->logger->info(Server::NAME . ' has started');

        $exit = $this->suspension->suspend();

        if ($exit instanceof WorkerProcess) {
            $this->runWorkerProcess($exit);
        }

        if ($exit instanceof PeriodicProcess) {
            $this->runPeriodicProcess($exit);
        }

        \assert(\is_int($exit));
        $this->onMasterShutdown();

        return $exit;
    }

    private function runWorkerProcess(WorkerProcess $worker): never
    {
        $supervisor = $this->supervisor;
        $this->free();
        exit($supervisor->runWorker($worker));
    }

    private function runPeriodicProcess(PeriodicProcess $worker): never
    {
        $scheduler = $this->scheduler;
        $this->free();
        exit($scheduler->runWorker($worker));
    }

    /**
     * Runs in master process
     *
     * @psalm-suppress InternalMethod false-positive
     */
    private function initServer(): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: master process  start_file=%s', Server::NAME, $this->startFile));
        }

        // Init event loop.
        EventLoop::setDriver(new StreamSelectDriver());
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = EventLoop::getDriver()->getSuspension();

        $this->serverStatusRelay = new Relay(\fopen($this->rxPipeFile, 'r+'), \fopen($this->txPipeFile, 'w+'));
        $this->serverStatusRelay->subscribe(
            ServerStatusRequest::class,
            fn() => $this->requestServerStatus($this->serverStatusRelay)
        );
        $this->serverStatusRelay->subscribe(
            ConnectionsRequest::class,
            fn() => $this->supervisor->requestServerConnections($this->serverStatusRelay)
        );
        $this->serverStatus->setRunning(true);

        $stopCallback = function (): void {
            $this->stop();
        };
        foreach ([SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT] as $signo) {
            EventLoop::onSignal($signo, $stopCallback);
        }
        EventLoop::onSignal(SIGUSR1, fn() => $this->reload());

        // Force run garbage collection periodically
        EventLoop::repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });

        foreach ($this->modules as $module) {
            $module->start($this);
        }
    }

    /**
     * Fork process
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

        if (false === \file_put_contents($this->pidFile, (string) \posix_getpid())) {
            throw new PHPStreamServerException(\sprintf('Can\'t save pid to %s', $this->pidFile));
        }

        if(!\file_exists($this->rxPipeFile)) {
            \posix_mkfifo($this->rxPipeFile, 0644);
        }

        if(!\file_exists($this->txPipeFile)) {
            \posix_mkfifo($this->txPipeFile, 0644);
        }
    }

    private function onMasterShutdown(): void
    {
        if (\file_exists($this->pidFile)) {
            \unlink($this->pidFile);
        }

        if (\file_exists($this->rxPipeFile)) {
            \unlink($this->rxPipeFile);
        }

        if (\file_exists($this->txPipeFile)) {
            \unlink($this->txPipeFile);
        }
    }

    public function stop(int $code = 0): void
    {
        if (!$this->isRunning()) {
            $this->logger->error(Server::NAME . ' is not running');
            return;
        }

        $this->logger->info(Server::NAME . ' stopping ...');

        if (($masterPid = $this->getPid()) !== \posix_getpid()) {
            // If it called from outside working process
            \posix_kill($masterPid, SIGTERM);
            return;
        }

        if ($this->status === Status::SHUTDOWN) {
            return;
        }

        $this->status = Status::SHUTDOWN;

        $stopFutures = [];
        $stopFutures[] = $this->supervisor->stop();
        $stopFutures[] = $this->scheduler->stop();
        foreach ($this->modules as $module) {
            if (null !== $future = $module->stop()) {
                $stopFutures[] = $future;
            }
        }

        awaitAll($stopFutures);

        $this->logger->info(Server::NAME . ' stopped');
        $this->suspension->resume($code);
    }

    public function reload(): void
    {
        if (!$this->isRunning()) {
            $this->logger->error(Server::NAME . ' is not running');
            return;
        }

        $masterPid = $this->getPid();

        if ($masterPid !== \posix_getppid()) {
            $this->logger->info(Server::NAME . ' reloading ...');
        }

        // If it called from outside working process
        if ($masterPid !== \posix_getpid()) {
            \posix_kill($masterPid, SIGUSR1);
            return;
        }

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

    public function getStatus(): Status
    {
        return $this->status;
    }

    private function free(): void
    {
        EventLoop::queue(static fn() => EventLoop::getDriver()->stop());
        EventLoop::getDriver()->run();

        foreach ($this->modules as $module) {
            $module->free();
        }

        // Delete all references
        foreach ($this as $prop => $val) {
            try {
                unset($this->$prop);
            } catch (\Error) {
                // Ignore
            }
        }
    }

    private function requestServerStatus(Relay $relay): void
    {
        $relay->publish($this->serverStatus);
    }

    public function getServerStatus(): ServerStatus
    {
        if ($this->status === Status::RUNNING || !$this->isRunning() || !\file_exists($this->rxPipeFile) || !\file_exists($this->txPipeFile)) {
            return $this->serverStatus;
        }

        $suspension = EventLoop::getSuspension();
        $pipe = new Relay(\fopen($this->txPipeFile, 'r+'), \fopen($this->rxPipeFile, 'w+'));
        $pipe->publish(new ServerStatusRequest());
        $pipe->subscribe(ServerStatus::class, static fn(ServerStatus $message): null => $suspension->resume($message));

        /** @var ServerStatus */
        return $suspension->suspend();
    }

    /**
     * @return list<Connection>
     */
    public function getServerConnections(): array
    {
        if (!$this->isRunning() || !\file_exists($this->rxPipeFile) || !\file_exists($this->txPipeFile)) {
            return [];
        }

        $suspension = EventLoop::getSuspension();
        $pipe = new Relay(\fopen($this->txPipeFile, 'r+'), \fopen($this->rxPipeFile, 'w+'));
        $pipe->publish(new ConnectionsRequest());
        $pipe->subscribe(Connections::class, static fn(Connections $message): null => $suspension->resume($message->connections));

        /** @var list<Connection> */
        return $suspension->suspend();
    }
}
