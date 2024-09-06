<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\ProcessTrait;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Spawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatusAwareInterface;
use Luzrain\PHPStreamServer\Plugin\WorkerModule;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyAwareInterface;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyTrigger;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

final class WorkerProcess implements WorkerProcessInterface, ReloadStrategyAwareInterface, TrafficStatusAwareInterface
{
    use ProcessTrait {
        detach as detachByTrait;
    }

    private const GC_PERIOD = 180;

    private TrafficStatus $trafficStatus;
    private ReloadStrategyTrigger $reloadStrategyTrigger;
    private MessageBus $messageBus;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     */
    public function __construct(
        string $name = 'none',
        public readonly int $count = 1,
        private readonly bool $reloadable = true,
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

        /** @psalm-suppress InaccessibleProperty */
        $this->pid = \posix_getpid();

        $this->messageBus = new SocketFileMessageBus($this->socketFile);
        $this->trafficStatus = new TrafficStatus($this->messageBus);
        $this->reloadStrategyTrigger = new ReloadStrategyTrigger($this->reload(...));

        EventLoop::setDriver((new DriverFactory())->create());

        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->emitReloadEvent($exception);
        });

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

    public function startWorkerModule(WorkerModule $module): void
    {
        $module->start($this);
    }

    public function addReloadStrategy(ReloadStrategyInterface ...$reloadStrategies): void
    {
        $this->reloadStrategyTrigger->addReloadStrategy(...$reloadStrategies);
    }

    public function emitReloadEvent(mixed $event): void
    {
        $this->reloadStrategyTrigger->emitEvent($event);
    }

    public function getTrafficStatus(): TrafficStatus
    {
        return $this->trafficStatus;
    }
}
