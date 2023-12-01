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
    private array $socketCallbackMap;

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

    public function addChild(WorkerProcess $worker, int $pid, string $socketCallbackId): void
    {
        if (!isset($this->pool[spl_object_id($worker)])) {
            throw new PHPRunnerException('Worker is not fount in pool');
        }

        $this->pidMap[$worker][] = $pid;
        $this->socketCallbackMap[$pid] = $socketCallbackId;
    }

    public function deleteChild(int $pid): void
    {
        $worker = $this->getWorkerByPid($pid);
        $pids = $this->pidMap[$worker];
        unset($pids[\array_search($pid, $pids)]);
        $this->pidMap[$worker] = \array_values($pids);
        unset($this->socketCallbackMap[$pid]);
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

    public function getWorkerCount(): int
    {
        return \count($this->pool);
    }

    public function getSocketCallbackIdByPid(int $pid): string
    {
        return $this->socketCallbackMap[$pid];
    }
}
