<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use function Amp\async;

abstract class Plugin
{
    /**
     * Initialize module before start
     */
    public function init(MasterProcess $masterProcess): void
    {
    }

    /**
     * Start module
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
     * Register commands
     *
     * @return iterable<Command>
     */
    public function commands(): iterable
    {
        return [];
    }
}
