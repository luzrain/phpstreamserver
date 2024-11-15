<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Internal;

use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Counter;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Gauge;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\RegistryInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Message\ProcessStartedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Status\SchedulerStatus;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Server;
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
