<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;
use Luzrain\PHPStreamServer\ReloadStrategy\TimerReloadStrategy;
use Revolt\EventLoop;

final class ReloadStrategyTrigger
{
    /** @var array<ReloadStrategy> */
    private array $reloadStrategies = [];

    public function __construct(private readonly \Closure $reloadCallback)
    {
    }

    public function addReloadStrategies(ReloadStrategy ...$reloadStrategies): void
    {
        \array_push($this->reloadStrategies, ...$reloadStrategies);

        foreach ($reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy instanceof TimerReloadStrategy) {
                EventLoop::repeat($reloadStrategy->getInterval(), function () use ($reloadStrategy): void {
                    $reloadStrategy->shouldReload($reloadStrategy::EVENT_CODE_TIMER) && $this->reload();
                });
            }
        }
    }

    public function emitRequest(mixed $request): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload(ReloadStrategy::EVENT_CODE_REQUEST, $request)) {
                $this->reload();
                break;
            }
        }
    }

    public function emitException(\Throwable $throwable): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload(ReloadStrategy::EVENT_CODE_EXCEPTION, $throwable)) {
                $this->reload();
                break;
            }
        }
    }

    private function reload(): void
    {
        EventLoop::defer(function (): void {
            ($this->reloadCallback)();
        });
    }
}
