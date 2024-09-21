<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\WorkerProcessInterface;
use Revolt\EventLoop;

/**
 * @internal
 */
final class WorkerPool
{
    private const BLOCKED_LABEL_PERSISTENCE = 30;
    public const BLOCK_WARNING_TRESHOLD = 6;

    /**
     * @var array<int, WorkerProcessInterface>
     */
    private array $workerPool = [];

    /**
     * @var array<int, array<int, Process>>
     */
    private array $processMap;

    public function __construct()
    {
    }

    public function registerWorker(WorkerProcessInterface $worker): void
    {
        $this->workerPool[$worker->getId()] = $worker;
        $this->processMap[$worker->getId()] = [];
    }

    public function addChild(WorkerProcessInterface $worker, int $pid): void
    {
        if (!isset($this->workerPool[$worker->getId()])) {
            throw new PHPStreamServerException('Worker is not found in pool');
        }

        $this->processMap[$worker->getId()][$pid] = new Process($pid);
    }

    public function markAsDeleted(int $pid): void
    {
        if (null !== $worker = $this->getWorkerByPid($pid)) {
            unset($this->processMap[$worker->getId()][$pid]);
        }
    }

    public function markAsDetached(int $pid): void
    {
        if (null !== $worker = $this->getWorkerByPid($pid)) {
            $this->processMap[$worker->getId()][$pid]->detached = true;
        }
    }

    public function markAsBlocked(int $pid): void
    {
        if (null !== $worker = $this->getWorkerByPid($pid)) {
            $this->processMap[$worker->getId()][$pid]->blocked = true;
            EventLoop::delay(self::BLOCKED_LABEL_PERSISTENCE, function () use ($worker, $pid) {
                if (isset($this->processMap[$worker->getId()][$pid])) {
                    $this->processMap[$worker->getId()][$pid]->blocked = false;
                }
            });
        }
    }

    public function markAsHealthy(int $pid, int $time): void
    {
        if (null !== $worker = $this->getWorkerByPid($pid)) {
            $this->processMap[$worker->getId()][$pid]->blocked = false;
            $this->processMap[$worker->getId()][$pid]->time = $time;
        }
    }

    public function getWorkerByPid(int $pid): WorkerProcessInterface|null
    {
        foreach ($this->processMap as $workerId => $processes) {
            if (\in_array($pid, \array_keys($processes), true)) {
                return $this->workerPool[$workerId];
            }
        }

        return null;
    }

    /**
     * @return \Iterator<WorkerProcessInterface>
     * @psalm-return iterable<WorkerProcessInterface>
     */
    public function getRegisteredWorkers(): \Iterator
    {
        return new \ArrayIterator($this->workerPool);
    }

    /**
     * @return \Iterator<int>
     */
    public function getAliveWorkerPids(WorkerProcessInterface $worker): \Iterator
    {
        return new \ArrayIterator(\array_keys($this->processMap[$worker->getId()] ?? []));
    }

    /**
     * @return \Iterator<WorkerProcessInterface, Process>
     */
    public function getProcesses(): \Iterator
    {
        foreach ($this->processMap as $workerId => $processes) {
            foreach ($processes as $process) {
                yield $this->workerPool[$workerId] => $process;
            }
        }
    }

    public function getWorkerCount(): int
    {
        return \count($this->workerPool);
    }

    public function getProcessesCount(): int
    {
        return \iterator_count($this->getProcesses());
    }
}
