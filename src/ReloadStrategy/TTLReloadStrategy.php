<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker after $ttl working time
 */
final class TTLReloadStrategy implements ReloadStrategyInterface
{
    public const EXIT_CODE = 101;

    /**
     * @param int $ttl TTL in seconds
     */
    public function __construct(public readonly int $ttl)
    {
    }

    public function onTimer(): bool
    {
        return false;
    }

    public function onRequest(): bool
    {
        return false;
    }

    public function onException(): bool
    {
        return false;
    }

    public function shouldReload(mixed $event = null): bool
    {
        return false;
    }
}
