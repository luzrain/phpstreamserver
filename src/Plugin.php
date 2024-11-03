<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\Future;
use Luzrain\PHPStreamServer\Console\Command;
use function Amp\async;

abstract class Plugin
{
    protected readonly ContainerInterface $masterContainer;
    protected readonly ContainerInterface $workerContainer;

    /**
     * @readonly
     */
    protected Status $status;

    /**
     * @internal
     */
    final public function register(ContainerInterface $masterContainer, ContainerInterface $workerContainer, Status &$status): void
    {
        $this->masterContainer = $masterContainer;
        $this->workerContainer = $workerContainer;
        $this->status = &$status;
        $this->init();
    }

    /**
     * Hanlde worker
     */
    public function addWorker(Process $worker): void
    {
    }

    /**
     * Initialize. Executes before startup
     */
    public function init(): void
    {
    }

    /**
     * Start plugin. Ecexutes during startup
     */
    public function start(): void
    {
    }

    /**
     * Send stop command to plugin
     * Master process will wait for plugin to finish
     */
    public function stop(): Future
    {
        return async(static fn() => null);
    }

    /**
     * Send reload command to plugin
     */
    public function reload(): void
    {
    }

    /**
     * Register commands
     *
     * @return iterable<Command>
     */
    public function commands(): iterable
    {
        return [];
    }
}
