<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Luzrain\PHPStreamServer\WorkerProcessInterface;

interface WorkerModule
{
    public function start(WorkerProcessInterface $worker): void;
}
