<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Luzrain\PHPStreamServer\WorkerProcess;

interface Plugin
{
    public function start(WorkerProcess $workerProcess): void;
}
