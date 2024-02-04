<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker after $ttl working time
 */
class TTLReloadStrategy implements TimerReloadStrategyInterface
{
    public const EXIT_CODE = 101;

    /**
     * @param int $ttl TTL in seconds
     */
    public function __construct(private readonly int $ttl)
    {
    }

    public function getInterval(): int
    {
        return $this->ttl;
    }

    public function shouldReload(int $eventCode, mixed $eventObject = null): bool
    {
        return $eventCode === self::EVENT_CODE_TIMER;
    }
}
