<?php

declare(strict_types=1);

namespace PHPStreamServer\SupervisorPlugin\Status;

use PHPStreamServer\MessageBus\MessageHandlerInterface;
use PHPStreamServer\SupervisorPlugin\Message\ProcessBlockedEvent;
use PHPStreamServer\SupervisorPlugin\Message\ProcessDetachedEvent;
use PHPStreamServer\SupervisorPlugin\Message\ProcessExitEvent;
use PHPStreamServer\SupervisorPlugin\Message\ProcessHeartbeatEvent;
use PHPStreamServer\SupervisorPlugin\Message\ProcessSpawnedEvent;
use PHPStreamServer\SupervisorPlugin\WorkerProcess;
use Revolt\EventLoop;
use function Amp\weakClosure;
use function PHPStreamServer\getCurrentUser;
use function PHPStreamServer\getMemoryUsageByPid;

final class SupervisorStatus
{
    /**
     * @var array<int, WorkerInfo>
     */
    private array $workers = [];

    /**
     * @var array<int, ProcessInfo>
     */
    private array $processes = [];

    public function __construct()
    {
    }

    public function subscribeToWorkerMessages(MessageHandlerInterface $handler): void
    {
        $handler->subscribe(ProcessSpawnedEvent::class, weakClosure(function (ProcessSpawnedEvent $message): void {
            $this->processes[$message->pid] = new ProcessInfo(
                workerId: $message->workerId,
                pid: $message->pid,
                user: $message->user,
                name: $message->name,
                startedAt: $message->startedAt,
            );
        }));

        $handler->subscribe(ProcessHeartbeatEvent::class, weakClosure(function (ProcessHeartbeatEvent $message): void {
            if (!isset($this->processes[$message->pid]) || $this->processes[$message->pid]?->detached === true) {
                return;
            }

            $this->processes[$message->pid]->memory = $message->memory;
            $this->processes[$message->pid]->blocked = false;
        }));

        $handler->subscribe(ProcessBlockedEvent::class, weakClosure(function (ProcessBlockedEvent $message): void {
            if (!isset($this->processes[$message->pid]) || $this->processes[$message->pid]?->detached === true) {
                return;
            }

            $this->processes[$message->pid]->blocked = true;
        }));

        $handler->subscribe(ProcessExitEvent::class, weakClosure(function (ProcessExitEvent $message): void {
            unset($this->processes[$message->pid]);
        }));

        $handler->subscribe(ProcessDetachedEvent::class, weakClosure(function (ProcessDetachedEvent $message): void {
            if (!isset($this->processes[$message->pid])) {
                return;
            }

            $this->processes[$message->pid]->detached = true;
            $this->processes[$message->pid]->blocked = false;

            $checkMemoryUsageClosure = function (string $id) use ($message) {
                isset($this->processes[$message->pid])
                    ? $this->processes[$message->pid]->memory = getMemoryUsageByPid($message->pid)
                    : EventLoop::cancel($id);
            };

            EventLoop::repeat(WorkerProcess::HEARTBEAT_PERIOD, $checkMemoryUsageClosure);
            EventLoop::delay(0.2, $checkMemoryUsageClosure);
        }));
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->workers[$worker->id] = new WorkerInfo(
            user: $worker->getUser() ?? getCurrentUser(),
            name: $worker->name,
            count: $worker->count,
        );
    }

    public function getWorkersCount(): int
    {
        return \count($this->workers);
    }

    /**
     * @return list<WorkerInfo>
     */
    public function getWorkers(): array
    {
        return \array_values($this->workers);
    }

    public function getProcessesCount(): int
    {
        return \count($this->processes);
    }

    /**
     * @return list<ProcessInfo>
     */
    public function getProcesses(): array
    {
        return \array_values($this->processes);
    }

    public function getTotalMemory(): int
    {
        return (int) \array_sum(\array_map(static fn(ProcessInfo $p): int => $p->memory, $this->processes));
    }
}
