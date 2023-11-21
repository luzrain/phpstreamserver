<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Exception\PHPRunnerException;

/**
 * @internal
 */
final class WorkerPool
{
    private array $pool;
    private \WeakMap $pidMap;

    public function __construct()
    {
        $this->pool = [];
        $this->pidMap = new \WeakMap();
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->pool[spl_object_id($worker)] = $worker;
        $this->pidMap[$worker] = [];
    }

    public function addPid(WorkerProcess $worker, int $pid): void
    {
        if (!isset($this->pool[spl_object_id($worker)])) {
            throw new PHPRunnerException('Worker is not fount in pool');
        }

        $this->pidMap[$worker][] = $pid;
    }

    public function deletePid(WorkerProcess $worker, int $pid): void
    {
        if (!isset($this->pool[spl_object_id($worker)])) {
            throw new PHPRunnerException('Worker is unregistered in pool');
        }

        $pids = $this->pidMap[$worker];
        unset($pids[\array_search($pid, $pids)]);
        $this->pidMap[$worker] = \array_values($pids);
    }

    public function getWorkerByPid(int $pid): WorkerProcess
    {
        foreach ($this->pidMap as $worker => $pids) {
            if (\in_array($pid, $pids)) {
                return $worker;
            }
        }

        throw new PHPRunnerException(sprintf('No workers found associated with %d pid', $pid));
    }

    /**
     * @return \Generator<WorkerProcess>
     */
    public function getWorkers(): \Generator
    {
        foreach ($this->pool as $key => $worker) {
            yield $worker;
        }
    }

    /**
     * @return \Generator<int>
     */
    public function getAliveWorkerPids(WorkerProcess $worker): \Generator
    {
        foreach ($this->pidMap[$worker] as $pids) {
            yield $pids;
        }
    }

    /**
     * @return \Generator<int>
     */
    public function getAlivePids(): \Generator
    {
        foreach ($this->pidMap as $pids) {
            foreach ($pids as $pid) {
                yield $pid;
            }
        }
    }
}
