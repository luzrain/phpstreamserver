<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

use Luzrain\PhpRunner\Exception\PhpRunnerException;
use Luzrain\PhpRunner\WorkerProcess;

/**
 * @internal
 */
final class WorkerPool
{
    private array $pool = [];
    private \WeakMap $pidMap;
    private array $socketMap = [];

    public function __construct()
    {
        $this->pidMap = new \WeakMap();
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->pool[\spl_object_id($worker)] = $worker;
        $this->pidMap[$worker] = [];
    }

    /**
     * @param resource $socket
     */
    public function addChild(WorkerProcess $worker, int $pid, mixed $socket): void
    {
        if (!isset($this->pool[\spl_object_id($worker)])) {
            throw new PhpRunnerException('Worker is not fount in pool');
        }

        $this->pidMap[$worker][] = $pid;
        $this->socketMap[$pid] = $socket;
    }

    public function deleteChild(int $pid): void
    {
        $worker = $this->getWorkerByPid($pid);
        $pids = $this->pidMap[$worker];
        unset($pids[\array_search($pid, $pids)]);
        $this->pidMap[$worker] = \array_values($pids);
        \fclose($this->socketMap[$pid]);
        unset($this->socketMap[$pid]);
    }

    public function getWorkerByPid(int $pid): WorkerProcess
    {
        foreach ($this->pidMap as $worker => $pids) {
            if (\in_array($pid, $pids)) {
                return $worker;
            }
        }

        throw new PhpRunnerException(\sprintf('No workers found associated with %d pid', $pid));
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

    public function getProcessesCount(): int
    {
        return \iterator_count($this->getAlivePids());
    }

    /**
     * @return resource
     */
    public function getChildSocketByPid(int $pid): mixed
    {
        return $this->socketMap[$pid];
    }
}
