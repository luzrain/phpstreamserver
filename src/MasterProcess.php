<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Exception\ServerAlreadyRunningException;
use Luzrain\PHPStreamServer\Exception\ServerIsShutdownException;
use Luzrain\PHPStreamServer\Internal\ArrayContainer;
use Luzrain\PHPStreamServer\Internal\Console\StdoutHandler;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\Logger\ConsoleLogger;
use Luzrain\PHPStreamServer\Internal\Logger\NullLogger;
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
use Luzrain\PHPStreamServer\Internal\WorkerContext;
use Luzrain\PHPStreamServer\Message\ContainerGetCommand;
use Luzrain\PHPStreamServer\Message\ContainerHasCommand;
use Luzrain\PHPStreamServer\Message\ContainerSetCommand;
use Luzrain\PHPStreamServer\Message\LogEntryEvent;
use Luzrain\PHPStreamServer\Message\ReloadServerCommand;
use Luzrain\PHPStreamServer\Message\StopServerCommand;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;
use function Amp\ByteStream\getStderr;
use function Amp\Future\await;

final class MasterProcess implements MessageHandler, MessageBus, Container
{
    private const GC_PERIOD = 300;

    private static bool $registered = false;
    private string $startFile;
    private string $pidFile;
    private string $socketFile;
    private Suspension $suspension;
    private Status $status = Status::SHUTDOWN;
    private MessageHandler&MessageBus $messageHandler;
    private SocketFileMessageBus $socketFileMessageBus;
    private Supervisor $supervisor;
    private Scheduler $scheduler;
    private Container $container;

    /**
     * @var array<class-string<Plugin>, Plugin>
     */
    private array $plugins = [];

    public function __construct(
        string|null $pidFile,
        int $stopTimeout,
        private LoggerInterface|null $logger,
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

        $this->supervisor = new Supervisor($this, $this->status, $stopTimeout);
        $this->scheduler = new Scheduler($this, $this->status);
        $this->container = new ArrayContainer();
    }

    public function addWorker(WorkerProcessInterface|PeriodicProcessInterface ...$workers): void
    {
        /** @var ServerStatus|null $status */
        $status = $this->container->get(ServerStatus::class);

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
        if ($ret instanceof ProcessInterface) {
            $workerProcess = $ret;
            $this->free();
            exit($workerProcess->run(new WorkerContext(
                socketFile: $this->socketFile,
            )));
        }

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

        // Init event loop.
        EventLoop::setDriver(new StreamSelectDriver());
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = EventLoop::getDriver()->getSuspension();

        if ($noOutput) {
            StdoutHandler::disableStdout();
            $this->logger = new NullLogger();
        } else {
            $this->logger = new ConsoleLogger(getStderr());
        }

        ErrorHandler::register($this->logger);
        $this->messageHandler = new SocketFileMessageHandler($this->socketFile);

        $this->messageHandler->subscribe(ContainerGetCommand::class, function (ContainerGetCommand $message) {
            return $this->container->get($message->id);
        });

        $this->messageHandler->subscribe(ContainerHasCommand::class, function (ContainerHasCommand $message) {
            return $this->container->has($message->id);
        });

        $this->messageHandler->subscribe(ContainerSetCommand::class, function (ContainerSetCommand $message) {
            $this->container->set($message->id, $message->value);
        });

        $this->messageHandler->subscribe(StopServerCommand::class, function (StopServerCommand $message) {
            $this->stop($message->code);
        });

        $this->messageHandler->subscribe(ReloadServerCommand::class, function () {
            $this->reload();
        });

        $this->messageHandler->subscribe(LogEntryEvent::class, function (LogEntryEvent $event) {
            EventLoop::queue(function () use ($event) {
                $this->logger->log($event->level, $event->message, $event->context);
            });
        });

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

        $this->status = Status::RUNNING;

        EventLoop::defer(function () {
            $this->logger->info(Server::NAME . ' has started');
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
        unset($this->supervisor);
        unset($this->scheduler);
        unset($this->container);
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
            return $this->messageHandler->dispatch($message);
        }

        $this->socketFileMessageBus ??= new SocketFileMessageBus($this->socketFile);
        return $this->socketFileMessageBus->dispatch($message);
    }

    public function set(string $id, mixed $value): void
    {
        if ($this->status === Status::STARTING || $this->status === Status::RUNNING || !$this->isRunning()) {
            $this->container->set($id, $value);
            return;
        }

        $this->socketFileMessageBus ??= new SocketFileMessageBus($this->socketFile);
        $this->socketFileMessageBus->dispatch(new ContainerSetCommand($id, $value))->await();
    }

    public function get(string $id): mixed
    {
        if ($this->status === Status::STARTING || $this->status === Status::RUNNING || !$this->isRunning()) {
            return $this->container->get($id);
        }

        $this->socketFileMessageBus ??= new SocketFileMessageBus($this->socketFile);
        return $this->socketFileMessageBus->dispatch(new ContainerGetCommand($id))->await();
    }

    public function has(string $id): bool
    {
        if ($this->status === Status::STARTING || $this->status === Status::RUNNING || !$this->isRunning()) {
            return $this->container->has($id);
        }

        $this->socketFileMessageBus ??= new SocketFileMessageBus($this->socketFile);
        return $this->socketFileMessageBus->dispatch(new ContainerHasCommand($id))->await();
    }
}
