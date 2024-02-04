<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker after each request.
 */
class EachRequestReloadStrategy implements ReloadStrategyInterface
{
    public function shouldReload(int $eventCode, mixed $eventObject = null): bool
    {
        return $eventCode === self::EVENT_CODE_REQUEST;
    }
}
