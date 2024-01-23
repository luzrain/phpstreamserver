<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

/**
 * Reload worker on every $maxRequests requests.
 * To prevent simultaneous restart of all workers $dispersionPercentage can be set.
 * 1000 $maxRequests and 20% $dispersionPercentage will restart between 800 and 1000
 */
final class MaxRequestsReloadStrategy implements ReloadStrategyInterface
{
    private int $requestsCount = 0;
    private int $maxRequests;

    public function __construct(int $maxRequests, int $dispersionPercentage = 0)
    {
        $minRequests = $maxRequests - (int) \round($maxRequests * $dispersionPercentage / 100);
        $this->maxRequests = \random_int($minRequests, $maxRequests);
    }

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
        return ++$this->requestsCount > $this->maxRequests;
    }
}
