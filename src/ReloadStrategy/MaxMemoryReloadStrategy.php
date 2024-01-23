<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker if worker memory usage has increased $maxMemory value
 */
final class MaxMemoryReloadStrategy implements ReloadStrategyInterface
{
    public const EXIT_CODE = 102;

    public function __construct(private readonly int $maxMemory)
    {
    }

    public function onTimer(): bool
    {
        return true;
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
        return \memory_get_peak_usage() > $this->maxMemory;
    }
}
