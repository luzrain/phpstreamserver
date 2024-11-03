<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\MasterProcessIntarface;
use Luzrain\PHPStreamServer\Process;
use function Amp\async;

abstract class Plugin
{
    /**
     * Hanlde worker
     */
    public function addWorker(Process $worker): void
    {
    }

    /**
     * Initialize. Ecexutes before start
     */
    public function init(MasterProcessIntarface $masterProcess): void
    {
    }

    /**
     * Start module. Ecexutes after start
     */
    public function start(): void
    {
    }

    /**
     * Wait for plugin finish some work
     */
    public function stop(): Future
    {
        return async(static fn() => null);
    }

    /**
     * Reload command
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
