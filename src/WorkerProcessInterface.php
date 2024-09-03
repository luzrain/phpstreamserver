<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\RunnableProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;

interface WorkerProcessInterface extends ProcessInterface, RunnableProcess
{
    /**
     * Stop worker with exit code
     */
    public function stop(int $code = 0): void;

    /**
     * Reload worker
     */
    public function reload(): void;

    /**
     * Add reload strategy for worker
     */
    public function addReloadStrategies(ReloadStrategy ...$reloadStrategies): void;

    /**
     * Start plugin in this worker
     */
    public function startPlugin(Plugin $plugin): void;
}
