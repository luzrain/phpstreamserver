<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor;

use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PHPStreamServer\Internal\ReloadStrategy\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Process;
use Revolt\EventLoop;

class WorkerProcess extends Process
{
    final public const RELOAD_EXIT_CODE = 100;
    private const GC_PERIOD = 180;

    private ReloadStrategyTrigger $reloadStrategyTrigger;
    private bool $isReloading = false;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     */
    public function __construct(
        string $name = 'none',
        public readonly int $count = 1,
        public readonly bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        \Closure|null $onStart = null,
        private readonly \Closure|null $onStop = null,
        private readonly \Closure|null $onReload = null,
    ) {
        parent::__construct(name: $name, user: $user, group: $group, onStart: $onStart, onStop: $this->onStop(...));
    }

    static public function handleBy(): array
    {
        return [SupervisorPlugin::class];
    }

    private function onStop(self $process): void
    {
        if ($this->isReloading && $this->onReload) {
            ($this->onReload)($process);
        } elseif (!$this->isReloading && $this->onStop) {
            ($this->onStop)($process);
        }
    }

    protected function start(): void
    {
        EventLoop::onSignal(SIGUSR1, fn() => $this->reload());

        // Force run garbage collection periodically
        EventLoop::repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });

        $this->reloadStrategyTrigger = new ReloadStrategyTrigger($this->reload(...));

        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->reloadStrategyTrigger->emitEvent($exception);
        });
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

    public function stop(int $code = 0): void
    {
        $this->isReloading = $this->reloadable && $code === self::RELOAD_EXIT_CODE;
        parent::stop($code);
    }

    public function reload(): void
    {
        if ($this->reloadable) {
            $this->stop(self::RELOAD_EXIT_CODE);
        }
    }
}
