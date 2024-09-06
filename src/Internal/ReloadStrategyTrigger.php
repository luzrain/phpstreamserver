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

    public function addReloadStrategy(ReloadStrategy ...$reloadStrategies): void
    {
        foreach ($reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy instanceof TimerReloadStrategy) {
                EventLoop::repeat($reloadStrategy->getInterval(), function () use ($reloadStrategy): void {
                    $reloadStrategy->shouldReload($reloadStrategy::EVENT_CODE_TIMER) && $this->reload();
                });
            } else {
                $this->reloadStrategies[] = $reloadStrategy;
            }
        }
    }

    public function emitEvent(mixed $request): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            $eventCode = $request instanceof \Throwable ? ReloadStrategy::EVENT_CODE_EXCEPTION : ReloadStrategy::EVENT_CODE_REQUEST;
            if ($reloadStrategy->shouldReload($eventCode, $request)) {
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
