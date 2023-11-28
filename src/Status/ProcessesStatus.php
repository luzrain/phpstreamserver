<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

use Luzrain\PhpRunner\WorkerPool;
use Luzrain\PhpRunner\WorkerProcess;

final class ProcessesStatus
{
    public function __construct(private WorkerPool $pool)
    {
    }

    /**
     * @return array{
     *     processes: array{},
     *     total: array{},
     * }
     */
    public function getData(): array
    {
        $rows = [
            'processes' => [],
            'total' => [],
        ];

        foreach ($this->pool->getWorkers() as $worker) {
            $rows['processes'][] = [
                'pid' => '-',
                'user' => '-',
                'memory' => '0M',
                'name' => $worker->name,
                'connections' => 0,
                'requests' => 0,
            ];
        }

        $rows['total'] = [
            'user' => '-',
            'pid' => '-',
            'memory' => '0M',
            'name' => '-',
            'connections' => 0,
            'requests' => 0,
        ];

        return $rows;
    }
}
