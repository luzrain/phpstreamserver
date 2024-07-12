<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\WorkerProcess;

/**
 * @internal
 */
final class WorkerPool
{
    /**
     * @var array<int, WorkerProcess>
     */
    private array $pool = [];

    /**
     * @var \WeakMap<WorkerProcess, list<int>>
     */
    private \WeakMap $pidMap;

    public function __construct()
    {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->pidMap = new \WeakMap();
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->pool[\spl_object_id($worker)] = $worker;
        $this->pidMap[$worker] = [];
    }

    public function addChild(WorkerProcess $worker, int $pid): void
    {
        if (!isset($this->pool[\spl_object_id($worker)])) {
            throw new PHPStreamServerException('Worker is not fount in pool');
        }

        /** @psalm-suppress InvalidArgument */
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
    public function getWorkers(): \Iterator
    {
        return new \ArrayIterator($this->pool);
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
        return \count($this->pool);
    }

    public function getProcessesCount(): int
    {
        return \iterator_count($this->getAlivePids());
    }
}
