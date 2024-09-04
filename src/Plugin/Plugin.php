<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;

interface Plugin
{
    /**
     * Initialize module before event loop starts
     */
    public function start(MasterProcess $masterProcess): void;

    /**
     * If module has to finish some work right before server stop, master process will wait for it
     */
    public function stop(): Future;
}
