<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Plugin\WorkerModule;

interface WorkerProcessInterface extends ProcessInterface
{
    final public const RELOAD_EXIT_CODE = 100;
    final public const HEARTBEAT_PERIOD = 2;

    /**
     * Stop worker with exit code
     */
    public function stop(int $code = 0): void;

    /**
     * Reload worker
     */
    public function reload(): void;

    /**
     * Start worker module in this worker
     */
    public function startWorkerModule(WorkerModule $module): void;

    /**
     * Count of processes
     */
    public function getProcessCount(): int;

    /**
     * Delay in seconds between processes restart
     */
    public function getProcessRestartDelay(): float;
}
