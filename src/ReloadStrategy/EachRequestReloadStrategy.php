<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\ReloadStrategy;

/**
 * Reload worker after each request.
 */
class EachRequestReloadStrategy implements ReloadStrategy
{
    public function shouldReload(int $eventCode, mixed $eventObject = null): bool
    {
        return $eventCode === self::EVENT_CODE_REQUEST;
    }
}
