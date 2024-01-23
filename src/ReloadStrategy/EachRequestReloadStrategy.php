<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker after each request.
 */
final class EachRequestReloadStrategy implements ReloadStrategyInterface
{
    public function onTimer(): bool
    {
        return false;
    }

    public function onRequest(): bool
    {
        return true;
    }

    public function onException(): bool
    {
        return false;
    }

    public function shouldReload(mixed $event = null): bool
    {
        return true;
    }
}
