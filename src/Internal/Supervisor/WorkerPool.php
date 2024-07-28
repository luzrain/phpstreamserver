<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\WorkerProcess;

/**
 * @internal
 */
final class WorkerPool
{
    /**
     * @var array<int, WorkerProcess>
     */
    private array $workerPool = [];

    /**
     * @var \WeakMap<WorkerProcess, list<int>>
     */
    private \WeakMap $pidMap;

    public function __construct()
    {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->pidMap = new \WeakMap();
    }

    public function registerWorkerProcess(WorkerProcess $worker): void
    {
        $this->workerPool[\spl_object_id($worker)] = $worker;
        $this->pidMap[$worker] = [];
    }

    public function addChild(WorkerProcess $worker, int $pid): void
    {
        if (!isset($this->workerPool[\spl_object_id($worker)])) {
            throw new PHPStreamServerException('Worker is not found in pool');
        }

        $this->pidMap[$worker][] = $pid;
    }

    public function deleteChild(int $pid): void
    {
        if (null !== $worker = $this->getWorkerByPid($pid)) {
            $pids = $this->pidMap[$worker] ?? [];
            unset($pids[\array_search($pid, $pids, true)]);
            $this->pidMap[$worker] = \array_values($pids);
        }
    }

    public function getWorkerByPid(int $pid): WorkerProcess|null
    {
        foreach ($this->pidMap as $worker => $pids) {
            if (\in_array($pid, $pids, true)) {
                return $worker;
            }
        }

        return null;
    }

    /**
     * @return \Iterator<WorkerProcess>
     */
    public function getRegisteredWorkers(): \Iterator
    {
        return new \ArrayIterator($this->workerPool);
    }

    /**
     * @return \Iterator<int>
     */
    public function getAliveWorkerPids(WorkerProcess $worker): \Iterator
    {
        return new \ArrayIterator($this->pidMap[$worker]);
    }

    /**
     * @return \Iterator<int>
     */
    public function getAlivePids(): \Iterator
    {
        foreach ($this->pidMap as $pids) {
            foreach ($pids as $pid) {
                yield $pid;
            }
        }
    }

    public function getWorkerCount(): int
    {
        return \count($this->workerPool);
    }

    public function getProcessesCount(): int
    {
        return \iterator_count($this->getAlivePids());
    }
}
