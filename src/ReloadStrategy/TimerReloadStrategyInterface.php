<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\ReloadStrategy;

interface TimerReloadStrategyInterface extends ReloadStrategyInterface
{
    /**
     * Strategy will be triggered repeatedly every N seconds.
     *
     * @return int Timer interval in seconds
     */
    public function getInterval(): int;
}
