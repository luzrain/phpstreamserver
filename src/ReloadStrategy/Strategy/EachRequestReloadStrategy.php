<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\ReloadStrategy\Strategy;

use Amp\Http\Server\Request;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;

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
