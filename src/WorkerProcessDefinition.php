<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\WorkerProcess;

class WorkerProcessDefinition
{
    /**
     * @param null|\Closure(WorkerProcess):void $onStart
     * @param null|\Closure(WorkerProcess):void $onStop
     * @param null|\Closure(WorkerProcess):void $onReload
     */
    public function __construct(
        public string $name = 'none',
        public int $count = 1,
        public bool $reloadable = true,
        public string|null $user = null,
        public string|null $group = null,
        public \Closure|null $onStart = null,
        public \Closure|null $onStop = null,
        public \Closure|null $onReload = null,
    ) {
    }
}
