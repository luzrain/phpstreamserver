<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Spawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcessDefinition;
use Luzrain\PHPStreamServer\WorkerProcessInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

final class WorkerProcess implements RunnableProcess, WorkerProcessInterface
{
    public const RELOAD_EXIT_CODE = 100;
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
    private function __construct(
        public readonly string $name,
        public readonly int $count,
        private readonly bool $reloadable,
        private string|null $user,
        private string|null $group,
        private \Closure|null $onStart,
        private \Closure|null $onStop,
        private \Closure|null $onReload,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
    }

    public static function createFromDefinition(WorkerProcessDefinition $definition): self
    {
        return new self(
            name: $definition->name,
            count: $definition->count,
            reloadable: $definition->reloadable,
            user: $definition->user,
            group: $definition->group,
            onStart: $definition->onStart,
            onStop: $definition->onStop,
            onReload: $definition->onReload,
        );
    }

    /**
     * @internal
     */
    public function run(WorkerContext $workerContext): int
    {
        $this->socketFile = $workerContext->socketFile;
        $this->logger = $workerContext->logger;
        $this->setUserAndGroup();
        $this->initWorker();
        EventLoop::run();

        return $this->exitCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
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

        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->reloadStrategyTrigger->emitException($exception);
        });

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

    public function stop(int $code = 0): void
    {
        $this->exitCode = $code;
        try {
            $this->onStop !== null && ($this->onStop)($this);
        } finally {
            EventLoop::getDriver()->stop();
        }
    }

    public function reload(): void
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
        unset($this->logger);
        unset($this->trafficStatus);
        unset($this->reloadStrategyTrigger);
        unset($this->socketFile);
        unset($this->messageBus);
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

    public function getUser(): string
    {
        return $this->user ?? Functions::getCurrentUser();
    }

    public function getGroup(): string
    {
        return $this->group ?? Functions::getCurrentGroup();
    }

    public function addReloadStrategies(ReloadStrategy ...$reloadStrategies): void
    {
        $this->reloadStrategyTrigger->addReloadStrategies(...$reloadStrategies);
    }

    public function startPlugin(Plugin $plugin): void
    {
        $plugin->start($this);
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
