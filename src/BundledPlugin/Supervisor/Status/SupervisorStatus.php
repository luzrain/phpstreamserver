<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status;

use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessBlockedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessExitEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\WorkerProcess;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use function Amp\weakClosure;

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

    public function subscribeToWorkerMessages(MessageHandler $handler): void
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
            $this->processes[$message->pid]->memory = 0;
            $this->processes[$message->pid]->blocked = false;
        }));
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->workers[$worker->id] = new WorkerInfo(
            user: $worker->getUser() ?? Functions::getCurrentUser(),
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
