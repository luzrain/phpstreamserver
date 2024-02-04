<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker if worker memory usage has increased $maxMemory value
 */
class MaxMemoryReloadStrategy implements TimerReloadStrategyInterface
{
    public const EXIT_CODE = 102;
    private const TIMER_INTERVAL = 30;

    public function __construct(private readonly int $maxMemory)
    {
    }

    public function getInterval(): int
    {
        return self::TIMER_INTERVAL;
    }

    public function shouldReload(int $eventCode, mixed $eventObject = null): bool
    {
        if ($eventCode !== self::EVENT_CODE_TIMER) {
            return false;
        }

        return \max(\memory_get_peak_usage(), \memory_get_usage()) > $this->maxMemory;
    }
}
