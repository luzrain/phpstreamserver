<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\ReloadStrategy;

use Revolt\EventLoop;

/**
 * @internal
 */
final class ReloadStrategyTrigger
{
    /** @var list<ReloadStrategyInterface> */
    private array $reloadStrategies = [];

    /**
     * @param array<ReloadStrategyInterface> $reloadStrategies
     */
    public function __construct(private readonly \Closure $reloadCallback, array $reloadStrategies = [])
    {
        $this->addReloadStrategy(...$reloadStrategies);
    }

    public function addReloadStrategy(ReloadStrategyInterface ...$reloadStrategies): void
    {
        foreach ($reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy instanceof TimerReloadStrategyInterface) {
                EventLoop::repeat($reloadStrategy->getInterval(), function () use ($reloadStrategy): void {
                    if($reloadStrategy->shouldReload()) {
                        $this->reload();
                    }
                });
            } else {
                $this->reloadStrategies[] = $reloadStrategy;
            }
        }
    }

    /**
     * @param mixed $event any value that checked by reload strategies. Could be exception, request etc.
     */
    public function emitEvent(mixed $event): void
    {
        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload($event)) {
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
