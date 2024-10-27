<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\Event\ContainerGetCommand;
use Luzrain\PHPStreamServer\Internal\Event\ContainerHasCommand;
use Luzrain\PHPStreamServer\Internal\Event\ContainerSetCommand;
use Luzrain\PHPStreamServer\Internal\Event\ReloadServerCommand;
use Luzrain\PHPStreamServer\Internal\Event\StopServerCommand;
use Luzrain\PHPStreamServer\Internal\Logger\ConsoleLogger;
use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageHandler;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\Process;
use Luzrain\PHPStreamServer\Server;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;
use function Amp\Future\await;

final class MasterProcess
{
    private const GC_PERIOD = 300;

    private static bool $registered = false;
    private Suspension $suspension;
    public Status $status = Status::SHUTDOWN;
    private MessageHandler $messageHandler;
    private LoggerInterface $logger;
    /**
     * @readonly
     * @psalm-allow-private-mutation
     */
    public Container $masterContainer;
    public readonly Container $workerContainer;

    /**
     * @var array<class-string<Plugin>, Plugin>
     */
    private array $plugins = [];

    /**
     * @param array<Plugin> $plugins
     * @param array<Process> $workers
     */
    public function __construct(
        private readonly string $pidFile,
        private readonly string $socketFile,
        array $plugins,
        array $workers,
    ) {
        if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
            throw new PHPStreamServerException('Works in command line mode only');
        }

        if (self::$registered) {
            throw new PHPStreamServerException('Only one instance of server can be instantiated');
        }

        self::$registered = true;

        $this->masterContainer = new Container();
        $this->workerContainer = new Container();

        // Init event loop.
        EventLoop::setDriver(new StreamSelectDriver());
        $this->suspension = EventLoop::getDriver()->getSuspension();

        $this->masterContainer->set('suspension', $this->suspension);
        $this->masterContainer->register('handler', fn() => new SocketFileMessageHandler($this->socketFile));
        $this->masterContainer->alias('bus', 'handler');
        $this->masterContainer->register('logger', $defaultLogger = static fn() => new ConsoleLogger());
        $this->masterContainer->set('pid_file', $this->pidFile);
        $this->masterContainer->set('socket_file', $this->socketFile);
        $this->workerContainer->register('bus', fn() => new SocketFileMessageBus($this->socketFile));
        $this->workerContainer->register('logger', $defaultLogger);
        $this->workerContainer->set('pid_file', $this->pidFile);
        $this->workerContainer->set('socket_file', $this->socketFile);

        $this->addPlugin(...$plugins);
        $this->addWorker(...$workers);
    }

    public function addPlugin(Plugin ...$plugins): void
    {
        if ($this->status !== Status::SHUTDOWN) {
            throw new PHPStreamServerException('Cannot add plugin on running server');
        }

        foreach ($plugins as $plugin) {
            if (isset($this->plugins[$plugin::class])) {
                throw new PHPStreamServerException(\sprintf('Plugin "%s" already registered', $plugin::class));
            }
            $this->plugins[$plugin::class] = $plugin;
            $plugin->init($this);
        }
    }

    public function addWorker(Process ...$workers): void
    {
        if ($this->status !== Status::SHUTDOWN) {
            throw new PHPStreamServerException('Cannot add worker on running server');
        }

        foreach ($workers as $worker) {
            $workerCanBeHandled = false;
            foreach ($this->plugins as $plugin) {
                foreach ($plugin->workerSupports() as $workerClass) {
                    if ($worker instanceof $workerClass) {
                        $workerCanBeHandled = true;
                        $plugin->addWorker($worker);
                    }
                }
            }

            if (!$workerCanBeHandled) {
                throw new PHPStreamServerException(\sprintf('Worker "%s" can not be handled', $worker::class));
            }
        }
    }

    /**
     * @param array{daemonize?: bool, quiet?: bool} $options
     */
    public function run(array $options = []): int
    {
        if ($this->isRunning()) {
            throw new PHPStreamServerException(\sprintf('%s already running', Server::NAME));
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
        if ($ret instanceof Process) {
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
        $startFile = Functions::getStartFile();

        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: master process  start_file=%s', Server::NAME, $startFile));
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

        foreach ($this->plugins as $plugin) {
            $plugin->start();
        }

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

        EventLoop::defer(function () {
            $this->logger->info(Server::NAME . ' has started');
        });

        $this->status = Status::RUNNING;
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

        if (!\is_dir($socketFileDir = \dirname($this->socketFile))) {
            \mkdir(directory: $socketFileDir, recursive: true);
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

    private function stop(int $code = 0): void
    {
        if ($this->status !== Status::RUNNING) {
            return;
        }

        $this->status = Status::STOPPING;
        $this->logger->debug(Server::NAME . ' stopping ...');
        await(\array_map(fn (Plugin $p) => $p->stop(), $this->plugins));
        $this->status = Status::SHUTDOWN;
        $this->logger->info(Server::NAME . ' stopped');
        $this->suspension->resume($code);
    }

    private function reload(): void
    {
        if($this->status !== Status::RUNNING) {
            return;
        }

        $this->logger->info(Server::NAME . ' reloading ...');

        foreach ($this->plugins as $plugin) {
            $plugin->reload();
        }
    }

    public function isRunning(): bool
    {
        return $this->status === Status::RUNNING || Functions::isRunning($this->pidFile);
    }

    private function free(): void
    {
        ErrorHandler::unregister();
        SIGCHLDHandler::unregister();

        unset($this->plugins);
        unset($this->messageHandler);
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
}
