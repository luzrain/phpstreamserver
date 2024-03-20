<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Exception\PhpRunnerException;
use Luzrain\PHPStreamServer\Internal\ProcessMessage\ProcessInfo;
use Luzrain\PHPStreamServer\Internal\ProcessMessage\ProcessStatus;
use Luzrain\PHPStreamServer\Internal\Status\WorkerProcessStatus;

/**
 * @internal
 */
final class ProcessStatusPool
{
    /** @var array<int, ProcessInfo> */
    private array $processInfoMap = [];

    /** @var array<int, ProcessStatus> */
    private array $processStatusMap = [];

    /** @var array<int, \Closure(ProcessStatus): void> */
    private array $processStatusSubscriber = [];

    public function __construct()
    {
    }

    public function addProcessInfo(ProcessInfo $processInfo): void
    {
        $this->processInfoMap[$processInfo->getPid()] = $processInfo;
        if ($processInfo->isDetached) {
            unset($this->processStatusMap[$processInfo->getPid()]);
        }
    }

    public function addProcessStatus(ProcessStatus $processStatus): void
    {
        $this->processStatusMap[$processStatus->getPid()] = $processStatus;
        foreach ($this->processStatusSubscriber as $subscriber) {
            $subscriber($processStatus);
        }
    }

    /**
     * @param \Closure(ProcessStatus): void $callback
     */
    public function subscribeToProcessStatus(\Closure $callback): void
    {
        $this->processStatusSubscriber[\spl_object_id($callback)] = $callback;
    }

    /**
     * @param \Closure(ProcessStatus): void $callback
     */
    public function unSubscribeFromProcessStatus(\Closure $callback): void
    {
        unset($this->processStatusSubscriber[\spl_object_id($callback)]);
    }

    public function deleteProcess(int $pid): void
    {
        unset($this->processInfoMap[$pid]);
        unset($this->processStatusMap[$pid]);
    }

    /**
     * @return list<int>
     */
    public function getMonitoredPids(): array
    {
        $pids = [];
        foreach ($this->processInfoMap as $pid => $processStatus) {
            if (!$processStatus->isDetached) {
                $pids[] = $pid;
            }
        }

        return $pids;
    }

    public function getProcessSatus(int $pid): WorkerProcessStatus
    {
        if (!isset($this->processInfoMap[$pid])) {
            throw new PhpRunnerException(\sprintf('Cannot find child process with pid %s', $pid));
        }

        return new WorkerProcessStatus(
            pid: $pid,
            user: $this->processInfoMap[$pid]->user,
            memory: $this->processStatusMap[$pid]->memory ?? 0,
            name: $this->processInfoMap[$pid]->name,
            startedAt: $this->processInfoMap[$pid]->startedAt,
            listen: $this->processStatusMap[$pid]->listen ?? null,
            connectionStatistics: $this->processStatusMap[$pid]->connectionStatistics ?? null,
            connections: $this->processStatusMap[$pid]->connections ?? null,
        );
    }

    public function isDetached(int $pid): bool
    {
        return $this->processInfoMap[$pid]->isDetached ?? false;
    }
}
