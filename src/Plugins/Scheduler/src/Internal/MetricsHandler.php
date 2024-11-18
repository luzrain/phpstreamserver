<?php

declare(strict_types=1);

namespace PHPStreamServer\SchedulerPlugin\Internal;

use PHPStreamServer\BundledPlugin\Metrics\Counter;
use PHPStreamServer\BundledPlugin\Metrics\Gauge;
use PHPStreamServer\BundledPlugin\Metrics\RegistryInterface;
use PHPStreamServer\SchedulerPlugin\Message\ProcessStartedEvent;
use PHPStreamServer\SchedulerPlugin\Status\SchedulerStatus;
use PHPStreamServer\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Server;
use function Amp\weakClosure;

final readonly class MetricsHandler
{
    private Gauge $workersTotal;
    private Counter $runsTotal;

    public function __construct(
        RegistryInterface $registry,
        SchedulerStatus $schedulerStatus,
        MessageHandlerInterface $handler,
    ) {
        $this->workersTotal = $registry->registerGauge(
            namespace: Server::SHORTNAME,
            name: 'scheduler_tasks_total',
            help: 'Total number of tasks',
        );

        $this->runsTotal = $registry->registerCounter(
            namespace: Server::SHORTNAME,
            name: 'scheduler_task_runs_total',
            help: 'Total number of tasks call',
        );

        $handler->subscribe(ProcessStartedEvent::class, weakClosure(function (ProcessStartedEvent $message): void {
            $this->runsTotal->inc();
        }));

        $this->workersTotal->set($schedulerStatus->getPeriodicTasksCount());
    }
}
