<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

class WorkerProcessDefinition
{
    /**
     * @param null|\Closure(WorkerProcessInterface):void $onStart
     * @param null|\Closure(WorkerProcessInterface):void $onStop
     * @param null|\Closure(WorkerProcessInterface):void $onReload
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
