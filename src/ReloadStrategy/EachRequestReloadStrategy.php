<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\ReloadStrategy;

use Amp\Http\Server\Request;

/**
 * Reload worker after each request.
 */
final class EachRequestReloadStrategy implements ReloadStrategyInterface
{
    public function shouldReload(mixed $eventObject = null): bool
    {
        return $eventObject instanceof Request;
    }
}
