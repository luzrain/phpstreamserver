<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;

interface Module
{
    /**
     * Initialize module before event loop starts.
     */
    public function init(MasterProcess $masterProcess): void;

    /**
     * If module has to finish some work right before server stop, module can return Future and master process will wait for it.
     * If module can be stopped imidiatelly simply return null.
     */
    public function stop(): Future|null;

    /**
     * Free resources. This is necessary for not to waste memory in forked processes.
     */
    public function free(): void;
}
