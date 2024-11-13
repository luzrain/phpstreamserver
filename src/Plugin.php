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
    final public function registerPlugin(ContainerInterface $masterContainer, ContainerInterface $workerContainer, Status &$status): void
    {
        $this->masterContainer = $masterContainer;
        $this->workerContainer = $workerContainer;
        $this->status = &$status;
        $this->register();
    }

    /**
     * Hanlde worker
     */
    public function addWorker(Process $worker): void
    {
    }

    /**
     * Executes before start
     */
    protected function register(): void
    {
    }

    /**
     * Executes during startup
     */
    public function init(): void
    {
    }

    /**
     * Executes after startup
     */
    public function start(): void
    {
    }

    /**
     * Executes after the master process receives a stop command
     */
    public function stop(): Future
    {
        return async(static fn() => null);
    }

    /**
     * Executes after the master process receives a reload command
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
