<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Spawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess
{
    final public const STOP_EXIT_CODE = 0;
    final public const RELOAD_EXIT_CODE = 100;
    private const GC_PERIOD = 180;
    public const HEARTBEAT_PERIOD = 2;

    public readonly int $id;
    public readonly int $pid;
    private int $exitCode = 0;
    public LoggerInterface $logger;
    public TrafficStatus $trafficStatus;
    public ReloadStrategyTrigger $reloadStrategyTrigger;

    private string $socketFile;
    private MessageBus $messageBus;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     */
    public function __construct(
        public readonly string $name = 'none',
        public readonly int $count = 1,
        private readonly bool $reloadable = true,
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
        private \Closure|null $onReload = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
    }

    /**
     * @internal
     */
    final public function run(LoggerInterface $logger): int
    {
        $this->logger = $logger;
        $this->setUserAndGroup();
        $this->initWorker();
        EventLoop::getDriver()->run();

        return $this->exitCode;
    }

    private function setUserAndGroup(): void
    {
        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), ['worker' => $this->name]);
            $this->user = Functions::getCurrentUser();
        }
    }

    private function initWorker(): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: worker process  %s', Server::NAME, $this->name));
        }

        EventLoop::setDriver((new DriverFactory())->create());
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));
        /** @psalm-suppress InaccessibleProperty */
        $this->pid = \posix_getpid();

        $this->messageBus = new SocketFileMessageBus($this->socketFile);
        $this->trafficStatus = new TrafficStatus($this->messageBus);
        $this->reloadStrategyTrigger = new ReloadStrategyTrigger($this->reload(...));

        // onStart callback
        EventLoop::defer(function (): void {
            $this->onStart !== null && ($this->onStart)($this);
        });

        EventLoop::onSignal(SIGTERM, fn() => $this->stop());
        EventLoop::onSignal(SIGUSR1, fn() => $this->reload());

        EventLoop::queue(function () {
            $this->messageBus->dispatch(new Spawn(
                pid: $this->pid,
                user: $this->getUser(),
                name: $this->name,
                startedAt: new \DateTimeImmutable('now'),
            ));
        });

        EventLoop::queue($heartbeat = function (): void {
            $this->messageBus->dispatch(new Heartbeat(
                pid: $this->pid,
                memory: \memory_get_usage(),
                time: \hrtime(true),
            ));
        });

        EventLoop::repeat(self::HEARTBEAT_PERIOD, $heartbeat);

        // Force run garbage collection periodically
        EventLoop::repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });
    }

    final public function stop(int $code = self::STOP_EXIT_CODE): void
    {
        $this->exitCode = $code;
        try {
            $this->onStop !== null && ($this->onStop)($this);
        } finally {
            EventLoop::getDriver()->stop();
        }
    }

    final public function reload(): void
    {
        if (!$this->reloadable) {
            return;
        }

        $this->exitCode = self::RELOAD_EXIT_CODE;
        try {
            $this->onReload !== null && ($this->onReload)($this);
        } finally {
            EventLoop::getDriver()->stop();
        }
    }

    public function detach(): void
    {
        $this->messageBus->dispatch(new Detach($this->pid))->await();
        $identifiers = EventLoop::getDriver()->getIdentifiers();
        \array_walk($identifiers, EventLoop::getDriver()->cancel(...));
        EventLoop::getDriver()->stop();
        unset($this->trafficStatus, $this->reloadStrategyTrigger, $this->relay);
        $this->onStart = null;
        $this->onStop = null;
        $this->onReload = null;
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    /**
     * Give control to an external program and have it monitored by the master process.
     *
     * @param string $path path to a binary executable or a script
     * @param array $args array of argument strings passed to the program
     * @see https://www.php.net/manual/en/function.pcntl-exec.php
     */
    public function exec(string $path, array $args = []): never
    {
        $this->detach();
        $envVars = [...\getenv(), ...$_ENV];
        \pcntl_exec($path, $args, $envVars);
        exit(0);
    }

    final public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param \Closure(\Throwable):void $errorHandler
     */
    final public function setErrorHandler(\Closure $errorHandler): void
    {
        EventLoop::setErrorHandler(function (\Throwable $exception) use ($errorHandler) {
            $errorHandler($exception);
            $this->reloadStrategyTrigger->emitException($exception);
        });
    }

    final public function addReloadStrategies(ReloadStrategy ...$reloadStrategies): void
    {
        $this->reloadStrategyTrigger->addReloadStrategies(...$reloadStrategies);
    }

    final public function startPlugin(Plugin $plugin): void
    {
        $plugin->start($this);
    }

    final public function getUser(): string
    {
        return $this->user ?? Functions::getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? Functions::getCurrentGroup();
    }




    public function setPipe(string $socketFile): self
    {
        $this->socketFile = $socketFile;

        return $this;
    }







    /**
     * @template T
     * @param Message<T> $message
     * @return Future<T>
     */
    public function dispatch(Message $message): Future
    {
        return $this->messageBus->dispatch($message);
    }
}
