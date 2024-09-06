<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Scheduler;

use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\PeriodicProcessInterface;

/**
 * @internal
 */
final class WorkerPool
{
    /**
     * @var array<int, PeriodicProcessInterface>
     */
    private array $pool = [];

    /**
     * @var \WeakMap<PeriodicProcessInterface, int>
     */
    private \WeakMap $pidMap;

    public function __construct()
    {
        $this->pidMap = new \WeakMap();
    }

    public function addWorker(PeriodicProcessInterface $worker): void
    {
        $this->pool[\spl_object_id($worker)] = $worker;
    }

    public function addChild(PeriodicProcessInterface $worker, int $pid): void
    {
        if (!isset($this->pool[\spl_object_id($worker)])) {
            throw new PHPStreamServerException('PeriodicProcess is not fount in pool');
        }

        $this->pidMap[$worker] = $pid;
    }

    public function deleteChild(PeriodicProcessInterface $worker): void
    {
        unset($this->pidMap[$worker]);
    }

    public function getWorkerByPid(int $pid): PeriodicProcessInterface|null
    {
        foreach ($this->pidMap as $worker => $workerPid) {
            if ($pid === $workerPid) {
                return $worker;
            }
        }

        return null;
    }

    public function isWorkerRun(PeriodicProcessInterface $worker): bool
    {
        return isset($this->pidMap[$worker]);
    }

    /**
     * @return \Iterator<PeriodicProcessInterface>
     */
    public function getWorkers(): \Iterator
    {
        return new \ArrayIterator($this->pool);
    }

    public function getWorkerCount(): int
    {
        return \count($this->pool);
    }

    public function getProcessesCount(): int
    {
        return $this->pidMap->count();
    }
}
