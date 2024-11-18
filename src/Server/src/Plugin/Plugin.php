<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin;

use Amp\Future;
use PHPStreamServer\Console\Command;
use PHPStreamServer\Process;
use PHPStreamServer\Worker\ContainerInterface;
use PHPStreamServer\Worker\Status;
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
        $this->beforeStart();
    }

    /**
     * Hanlde worker
     */
    public function addWorker(Process $worker): void
    {
    }

    /**
     * Executes before startup
     */
    protected function beforeStart(): void
    {
    }

    /**
     * Executes during startup
     */
    public function onStart(): void
    {
    }

    /**
     * Executes after startup
     */
    public function afterStart(): void
    {
    }

    /**
     * Executes after the master process receives a stop command
     */
    public function onStop(): Future
    {
        return async(static fn() => null);
    }

    /**
     * Executes after the master process receives a reload command
     */
    public function onReload(): void
    {
    }

    /**
     * Register commands
     *
     * @return iterable<Command>
     */
    public function registerCommands(): iterable
    {
        return [];
    }
}
