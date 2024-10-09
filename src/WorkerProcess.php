<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\DeferredFuture;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\ProcessTrait;
use Luzrain\PHPStreamServer\Internal\ReloadStrategy\ReloadStrategyAwareInterface;
use Luzrain\PHPStreamServer\Internal\ReloadStrategy\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\NetworkTrafficCounter;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\NetworkTrafficCounterAwareInterface;
use Luzrain\PHPStreamServer\Message\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\Message\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\Message\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

final class WorkerProcess implements WorkerProcessInterface, ReloadStrategyAwareInterface, NetworkTrafficCounterAwareInterface
{
    use ProcessTrait {
        detach as detachByTrait;
    }

    private const GC_PERIOD = 180;

    private NetworkTrafficCounter $trafficStatus;
    private ReloadStrategyTrigger $reloadStrategyTrigger;
    private MessageBus $messageBus;
    private DeferredFuture|null $startFuture = null;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     */
    public function __construct(
        string $name = 'none',
        private readonly int $count = 1,
        private readonly bool $reloadable = true,
        private readonly float $restartDelay = 0,
        string|null $user = null,
        string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
        private \Closure|null $onReload = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
        $this->name = $name;
        $this->user = $user;
        $this->group = $group;
    }

    private function initWorker(): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: worker process  %s', Server::NAME, $this->name));
        }

        EventLoop::setDriver((new DriverFactory())->create());
        ErrorHandler::register($this->logger);

        $this->startFuture = new DeferredFuture();

        /** @psalm-suppress InaccessibleProperty */
        $this->pid = \posix_getpid();

        $this->messageBus = new SocketFileMessageBus($this->socketFile);
        $this->trafficStatus = new NetworkTrafficCounter($this->messageBus);
        $this->reloadStrategyTrigger = new ReloadStrategyTrigger($this->reload(...));

        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->emitReloadEvent($exception);
        });

        EventLoop::onSignal(SIGINT, fn() => null);
        EventLoop::onSignal(SIGTERM, fn() => $this->stop());
        EventLoop::onSignal(SIGUSR1, fn() => $this->reload());

        $this->messageBus->dispatch(new ProcessSpawnedEvent(
            workerId: $this->id,
            pid: $this->pid,
            user: $this->getUser(),
            name: $this->name,
            startedAt: new \DateTimeImmutable('now'),
        ))->await();

        EventLoop::queue($heartbeat = function (): void {
            $this->messageBus->dispatch(new ProcessHeartbeatEvent(
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

        // onStart callback
        if ($this->onStart !== null) {
            $this->startFuture->getFuture()->finally(function (): void {
                ($this->onStart)($this);
            });
        }

        $this->startFuture->complete();
        $this->startFuture = null;
    }

    public function stop(int $code = 0): void
    {
        $this->startFuture?->getFuture()->await();
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

        $this->startFuture?->getFuture()->await();
        $this->exitCode = self::RELOAD_EXIT_CODE;
        try {
            $this->onReload !== null && ($this->onReload)($this);
        } finally {
            EventLoop::getDriver()->stop();
        }
    }

    public function detach(): void
    {
        $this->startFuture?->getFuture()->await();
        $this->messageBus->dispatch(new ProcessDetachedEvent($this->pid))->await();
        $this->detachByTrait();
        unset($this->trafficStatus);
        unset($this->reloadStrategyTrigger);
        unset($this->messageBus);
        $this->onStart = null;
        $this->onStop = null;
        $this->onReload = null;
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    public function addReloadStrategy(ReloadStrategyInterface ...$reloadStrategies): void
    {
        $this->reloadStrategyTrigger->addReloadStrategy(...$reloadStrategies);
    }

    /**
     * @TODO get rid of this
     */
    public function emitReloadEvent(mixed $event): void
    {
        $this->reloadStrategyTrigger->emitEvent($event);
    }

    public function getNetworkTrafficCounter(): NetworkTrafficCounter
    {
        return $this->trafficStatus;
    }

    public function getProcessCount(): int
    {
        return $this->count;
    }

    public function getProcessRestartDelay(): float
    {
        return $this->restartDelay;
    }
}
