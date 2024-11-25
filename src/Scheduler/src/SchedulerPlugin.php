<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler;

use Amp\Future;
use PHPStreamServer\Core\Exception\ServiceNotFoundException;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Plugin\System\Connections\ConnectionsStatus;
use PHPStreamServer\Core\Plugin\System\Message\GetConnectionsStatusCommand;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Core\Worker\LoggerInterface;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use PHPStreamServer\Plugin\Scheduler\Message\GetSchedulerStatusCommand;
use Revolt\EventLoop\Suspension;
use PHPStreamServer\Plugin\Scheduler\Command\SchedulerCommand;
use PHPStreamServer\Plugin\Scheduler\Internal\MetricsHandler;
use PHPStreamServer\Plugin\Scheduler\Internal\Scheduler;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;

final class SchedulerPlugin extends Plugin
{
    private SchedulerStatus $schedulerStatus;
    private Scheduler $scheduler;
    private MessageHandlerInterface $handler;

    public function __construct()
    {
    }

    protected function beforeStart(): void
    {
        $this->scheduler = new Scheduler();
        $this->schedulerStatus = new SchedulerStatus();
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof PeriodicProcess);
        $this->scheduler->addWorker($worker);
        $this->schedulerStatus->addWorker($worker);
    }

    public function onStart(): void
    {
        $this->masterContainer->setService(SchedulerStatus::class, $this->schedulerStatus);

        /** @var Suspension $suspension */
        $suspension = $this->masterContainer->getService('main_suspension');
        $logger = $this->masterContainer->getService(LoggerInterface::class);
        $bus = $this->masterContainer->getService(MessageBusInterface::class);
        $this->handler = $this->masterContainer->getService(MessageHandlerInterface::class);

        $this->schedulerStatus->subscribeToWorkerMessages($this->handler);
        $this->scheduler->start($suspension, $logger, $bus);

        $schedulerStatus = $this->schedulerStatus;
        $this->handler->subscribe(GetSchedulerStatusCommand::class, static function () use ($schedulerStatus): SchedulerStatus {
            return $schedulerStatus;
        });
    }

    public function afterStart(): void
    {
        if (\interface_exists(RegistryInterface::class)) {
            try {
                $registry = $this->masterContainer->getService(RegistryInterface::class);
                $this->masterContainer->setService(MetricsHandler::class, new MetricsHandler($registry, $this->schedulerStatus, $this->handler));
            } catch (ServiceNotFoundException) {}
        }
    }

    public function onStop(): Future
    {
        return $this->scheduler->stop();
    }

    public function registerCommands(): iterable
    {
        return [
            new SchedulerCommand(),
        ];
    }
}
