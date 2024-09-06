<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;

interface PluginInterface
{
    /**
     * Initialize module before event loop starts
     */
    public function start(MasterProcess $masterProcess): void;

    /**
     * If plugin has to finish some work before server stop, master process will wait for it
     */
    public function stop(): Future;
}
