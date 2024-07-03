<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PHPStreamServer\ReloadStrategy\TimerReloadStrategyInterface;
use Revolt\EventLoop\Driver;

final class ReloadStrategyTrigger
{
    /** @var array<ReloadStrategyInterface> */
    private array $reloadStrategies = [];

    public function __construct(
        private readonly Driver $eventLoop,
        private readonly \Closure $reloadCallback,
    ) {
    }

    public function addReloadStrategies(ReloadStrategyInterface ...$reloadStrategies): void
    {
        \array_push($this->reloadStrategies, ...$reloadStrategies);

        foreach ($reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy instanceof TimerReloadStrategyInterface) {
                $this->eventLoop->repeat($reloadStrategy->getInterval(), function () use ($reloadStrategy): void {
                    $reloadStrategy->shouldReload($reloadStrategy::EVENT_CODE_TIMER) && $this->reload();
                });
            }
        }
    }

    public function emitRequest(mixed $request): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload(ReloadStrategyInterface::EVENT_CODE_REQUEST, $request)) {
                $this->reload();
                break;
            }
        }
    }

    public function emitException(\Throwable $throwable): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload(ReloadStrategyInterface::EVENT_CODE_EXCEPTION, $throwable)) {
                $this->reload();
                break;
            }
        }
    }

    private function reload(): void
    {
        $this->eventLoop->defer(function (): void {
            ($this->reloadCallback)();
        });
    }
}
