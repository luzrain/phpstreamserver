<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ReloadStrategy;

use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;

/**
 * @internal
 */
interface ReloadStrategyAwareInterface
{
    /**
     * Add reload strategy for worker
     */
    public function addReloadStrategy(ReloadStrategyInterface ...$reloadStrategies): void;

    /**
     * Emit event for checking by reload strategies
     */
    public function emitReloadEvent(mixed $event): void;
}
