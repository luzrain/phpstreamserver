<?php

declare(strict_types=1);

namespace PHPStreamServer\HttpServerPlugin\ReloadStrategy;

use Amp\Http\Server\Request;
use PHPStreamServer\Plugin\Supervisor\ReloadStrategy\ReloadStrategyInterface;

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