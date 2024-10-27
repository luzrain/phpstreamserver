<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\ReloadStrategy;

use Luzrain\PHPStreamServer\Internal\ReloadStrategy\TimerReloadStrategyInterface;

/**
 * Reload worker after $ttl working time
 */
final class TTLReloadStrategy implements TimerReloadStrategyInterface
{
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

    public function shouldReload(mixed $eventObject = null): bool
    {
        return true;
    }
}
