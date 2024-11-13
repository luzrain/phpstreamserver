<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Command\SchedulerCommand;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Internal\Scheduler;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Status\SchedulerStatus;
use Luzrain\PHPStreamServer\LoggerInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Plugin;
use Luzrain\PHPStreamServer\Process;
use Revolt\EventLoop\Suspension;

final class SchedulerPlugin extends Plugin
{
    private SchedulerStatus $schedulerStatus;
    private Scheduler $scheduler;

    public function __construct()
    {
        $this->schedulerStatus = new SchedulerStatus();
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof PeriodicProcess);
        $this->scheduler->addWorker($worker);
        $this->schedulerStatus->addWorker($worker);
    }

    public function init(): void
    {
        $this->scheduler = new Scheduler($this->status);
        $this->masterContainer->set(SchedulerStatus::class, $this->schedulerStatus);

        /** @var Suspension $suspension */
        $suspension = $this->masterContainer->get('suspension');
        /** @var LoggerInterface $logger */
        $logger = &$this->masterContainer->get('logger');
        /** @var MessageBusInterface $bus */
        $bus = &$this->masterContainer->get('bus');
        /** @var MessageHandlerInterface $handler */
        $handler = &$this->masterContainer->get('handler');

        $this->schedulerStatus->subscribeToWorkerMessages($handler);
        $this->scheduler->start($suspension, $logger, $bus);
    }

    public function stop(): Future
    {
        return $this->scheduler->stop();
    }

    public function commands(): iterable
    {
        return [
            new SchedulerCommand(),
        ];
    }
}
