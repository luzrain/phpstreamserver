<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

use Luzrain\PhpRunner\WorkerPool;
use Luzrain\PhpRunner\WorkerProcess;

final class WorkersStatus
{
    public function __construct(private WorkerPool $pool)
    {
    }

    /**
     * @return array{
     *     user: string,
     *     name: string,
     *     count: int,
     *     listen: string
     * }
     */
    public function getData(): array
    {
        return array_map(fn (WorkerProcess $worker) => [
            'user' => $worker->user ?? '??',
            'name' => $worker->name,
            'count' => $worker->count,
            'listen' => 'tcp://0.0.0.0:80',
        ], iterator_to_array($this->pool->getWorkers()));
    }
}
