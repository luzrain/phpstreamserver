<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Exception\ServerAlreadyRunningException;
use Luzrain\PHPStreamServer\Exception\ServerIsShutdownException;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\Logger\ConsoleLogger;
use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageHandler;
use Luzrain\PHPStreamServer\Internal\Scheduler\Scheduler;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\Internal\Status;
use Luzrain\PHPStreamServer\Internal\Supervisor\Supervisor;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Message\ContainerGetCommand;
use Luzrain\PHPStreamServer\Message\ContainerHasCommand;
use Luzrain\PHPStreamServer\Message\ContainerSetCommand;
use Luzrain\PHPStreamServer\Message\ReloadServerCommand;
use Luzrain\PHPStreamServer\Message\StopServerCommand;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Psr\Container\NotFoundExceptionInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;
use function Amp\Future\await;

final class MasterProcess implements MessageHandler, MessageBus
{
    private const GC_PERIOD = 300;

    private static bool $registered = false;
    private string $startFile;
    private string $pidFile;
    private string $socketFile;
    private Suspension $suspension;
    private Status $status = Status::SHUTDOWN;
    private MessageHandler $messageHandler;
    private MessageBus $messageBus;
    private Supervisor $supervisor;
    private Scheduler $scheduler;
    private LoggerInterface $logger;
    public Container $masterContainer;
    public readonly Container $workerContainer;

    /**
     * @var array<class-string<Plugin>, Plugin>
     */
    private array $plugins = [];

    public function __construct(
        string|null $pidFile,
        int $stopTimeout,
    ) {
        if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
            throw new \RuntimeException('Works in command line mode only');
        }

        if (self::$registered) {
            throw new \RuntimeException('Only one instance of server can be instantiated');
        }

        self::$registered = true;

        $this->startFile = Functions::getStartFile();

        $runDirectory = $pidFile === null
            ? (\posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir())
            : \pathinfo($pidFile, PATHINFO_DIRNAME)
        ;

        $this->pidFile = $pidFile ?? \sprintf('%s/phpss%s.pid', $runDirectory, \hash('xxh32', $this->startFile));
        $this->socketFile = \sprintf('%s/phpss%s.socket', $runDirectory, \hash('xxh32', $this->startFile . 'rx'));

        $this->masterContainer = new Container();
        $this->workerContainer = new Container();
        $this->supervisor = new Supervisor($this, $this->status, $stopTimeout);
        $this->scheduler = new Scheduler($this, $this->status);

        // Init event loop.
        EventLoop::setDriver(new StreamSelectDriver());
        $this->suspension = EventLoop::getDriver()->getSuspension();

        $defaultLogger = static fn() => new ConsoleLogger();
        $masterMessageHandler = fn() => new SocketFileMessageHandler($this->socketFile);
        $ipcMessageBus = fn() => new SocketFileMessageBus($this->socketFile);

        $this->masterContainer->set('suspension', $this->suspension);
        $this->masterContainer->register('handler', $masterMessageHandler);
        $this->masterContainer->alias('bus', 'handler');
        $this->masterContainer->register('ipc_bus', $ipcMessageBus);
        $this->masterContainer->register('logger', $defaultLogger);
        $this->workerContainer->register('bus', $ipcMessageBus);
        $this->workerContainer->register('logger', $defaultLogger);
    }

    public function addWorker(WorkerProcessInterface|PeriodicProcessInterface ...$workers): void
    {
        /** @var ServerStatus|null $status */
        try {
            $status = $this->masterContainer->get(ServerStatus::class);
        } catch (NotFoundExceptionInterface) {
            $status = null;
        }

        foreach ($workers as $worker) {
            if ($worker instanceof WorkerProcessInterface) {
                $this->supervisor->addWorker($worker);
            } elseif($worker instanceof PeriodicProcessInterface) {
                $this->scheduler->addWorker($worker);
            }
            $status?->addWorker($worker);
        }
    }

    public function addPlugin(Plugin ...$plugins): void
    {
        foreach ($plugins as $plugin) {
            $plugin->init($this);
            $this->plugins[] = $plugin;
        }
    }

    /**
     * @param array{daemonize?: bool, quiet?: bool} $options
     * @throws ServerAlreadyRunningException
     */
    public function run(array $options = []): int
    {
        if ($this->isRunning()) {
            throw new ServerAlreadyRunningException();
        }

        $daemonize = $options['daemonize'] ?? false;
        $quiet = $options['quiet'] ?? false;
        $isDaemonized = false;
        if ($daemonize && $this->doDaemonize()) {
            // Runs in caller process
            return 0;
        } elseif ($daemonize) {
            // Runs in daemonized master process
            $isDaemonized = true;
        }

        $this->start(noOutput: $isDaemonized || $quiet);

        $ret = $this->suspension->suspend();

        // child process start
        if ($ret instanceof ProcessInterface) {
            $this->free();
            exit($ret->run($this->workerContainer));
        }

        // master process shutdown
        \assert(\is_int($ret));
        $this->onMasterShutdown();
        return $ret;
    }

    /**
     * Runs in master process
     */
    private function start(bool $noOutput = false): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: master process  start_file=%s', Server::NAME, $this->startFile));
        }

        $this->status = Status::STARTING;

        $this->saveMasterPid();

//        if ($noOutput) {
//            StdoutHandler::disableStdout();
//            $this->logger = new NullLogger();
//        } else {
//            $this->logger = new ConsoleLogger();
//        }

        $this->logger = $this->masterContainer->get('logger');
        $this->messageHandler = $this->masterContainer->get('handler');
        $this->messageBus = $this->masterContainer->get('bus');

        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));

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

        $this->supervisor->start($this->suspension, $this->logger);
        $this->scheduler->start($this->suspension, $this->logger);

        $this->messageHandler->subscribe(ContainerGetCommand::class, function (ContainerGetCommand $message) {
            return $this->masterContainer->get($message->id);
        });

        $this->messageHandler->subscribe(ContainerHasCommand::class, function (ContainerHasCommand $message) {
            return $this->masterContainer->has($message->id);
        });

        $this->messageHandler->subscribe(ContainerSetCommand::class, function (ContainerSetCommand $message) {
            $this->masterContainer->set($message->id, $message->value);
        });

        $this->messageHandler->subscribe(StopServerCommand::class, function (StopServerCommand $message) {
            $this->stop($message->code);
        });

        $this->messageHandler->subscribe(ReloadServerCommand::class, function () {
            $this->reload();
        });

        $this->status = Status::RUNNING;

        EventLoop::defer(function () {
            $this->logger->info(Server::NAME . ' has started');
        });

        EventLoop::delay(3, function (){
            $this->logger->debug('TEST TEST TEST');
        });
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

    /**
     * @throws ServerIsShutdownException
     */
    public function stop(int $code = 0): void
    {
        if (!$this->isRunning()) {
            throw new ServerIsShutdownException();
        }

        // If it called from outside working process
        if ($this->getPid() !== \posix_getpid()) {
            echo Server::NAME ." stopping ...\n";
            $this->dispatch(new StopServerCommand($code))->await();
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

    /**
     * @throws ServerIsShutdownException
     */
    public function reload(): void
    {
        if (!$this->isRunning()) {
            throw new ServerIsShutdownException();
        }

        // If it called from outside working process
        if ($this->getPid() !== \posix_getpid()) {
            echo Server::NAME . " reloading ...\n";
            $this->dispatch(new ReloadServerCommand())->await();
            return;
        }

        $this->logger->info(Server::NAME . ' reloading ...');
        $this->supervisor->reload();
    }

    private function getPid(): int
    {
        return \is_file($this->pidFile) ? (int) \file_get_contents($this->pidFile) : 0;
    }

    public function isRunning(): bool
    {
        if ($this->status === Status::RUNNING) {
            return true;
        }

        return (0 !== $pid = $this->getPid()) && \posix_kill($pid, 0);
    }

    private function free(): void
    {
        ErrorHandler::unregister();
        SIGCHLDHandler::unregister();

        unset($this->plugins);
        unset($this->messageHandler);
        unset($this->messageBus);
        unset($this->supervisor);
        unset($this->scheduler);
        unset($this->masterContainer);
        unset($this->logger);

        EventLoop::queue(static function() {
            $identifiers = EventLoop::getDriver()->getIdentifiers();
            \array_walk($identifiers, EventLoop::getDriver()->cancel(...));
            EventLoop::getDriver()->stop();
        });
        EventLoop::getDriver()->run();

        \gc_collect_cycles();
        \gc_mem_caches();
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): mixed $closure
     */
    public function subscribe(string $class, \Closure $closure): void
    {
        if ($this->status !== Status::STARTING && $this->status !== Status::RUNNING) {
            throw new \RuntimeException(\sprintf('%s() can not be used from outside running process', __METHOD__));
        }

        $this->messageHandler->subscribe($class, $closure);
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): mixed $closure
     */
    public function unsubscribe(string $class, \Closure $closure): void
    {
        if ($this->status !== Status::STARTING && $this->status !== Status::RUNNING) {
            throw new \RuntimeException(\sprintf('%s() can not be used from outside running process', __METHOD__));
        }

        $this->messageHandler->unsubscribe($class, $closure);
    }

    /**
     * @template T
     * @param Message<T> $message
     * @return Future<T>
     * @throws ServerIsShutdownException
     */
    public function dispatch(Message $message): Future
    {
        if (!$this->isRunning()) {
            throw new ServerIsShutdownException();
        }

        if ($this->status === Status::RUNNING) {
            return $this->messageBus->dispatch($message);
        }

        /** @var MessageBus $ipcMessageBus */
        $ipcMessageBus = $this->masterContainer->get('ipc_bus');

        return $ipcMessageBus->dispatch($message);
    }
}
