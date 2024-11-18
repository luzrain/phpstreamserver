<?php

declare(strict_types=1);

namespace PHPStreamServer\SchedulerPlugin\Status;

use PHPStreamServer\SchedulerPlugin\Message\ProcessScheduledEvent;
use PHPStreamServer\SchedulerPlugin\PeriodicProcess;
use PHPStreamServer\MessageBus\MessageHandlerInterface;
use function Amp\weakClosure;

final class SchedulerStatus
{
    /**
     * @var array<int, PeriodicWorkerInfo>
     */
    private array $periodicWorkers = [];

    public function __construct()
    {
    }

    public function subscribeToWorkerMessages(MessageHandlerInterface $handler): void
    {
        $handler->subscribe(ProcessScheduledEvent::class, weakClosure(function (ProcessScheduledEvent $message): void {
            $this->periodicWorkers[$message->id]->nextRunDate = $message->nextRunDate;
        }));
    }

    public function addWorker(PeriodicProcess $worker): void
    {
        $this->periodicWorkers[$worker->id] = new PeriodicWorkerInfo(
            user: $worker->getUser(),
            name: $worker->name,
            schedule: $worker->schedule,
        );
    }

    public function getPeriodicTasksCount(): int
    {
        return \count($this->periodicWorkers);
    }

    /**
     * @return list<PeriodicWorkerInfo>
     */
    public function getPeriodicWorkers(): array
    {
        return \array_values($this->periodicWorkers);
    }
}
