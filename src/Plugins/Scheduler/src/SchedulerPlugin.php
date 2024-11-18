<?php

declare(strict_types=1);

namespace PHPStreamServer\SchedulerPlugin;

use Amp\Future;
use PHPStreamServer\BundledPlugin\Metrics\RegistryInterface;
use PHPStreamServer\SchedulerPlugin\Command\SchedulerCommand;
use PHPStreamServer\SchedulerPlugin\Internal\MetricsHandler;
use PHPStreamServer\SchedulerPlugin\Internal\Scheduler;
use PHPStreamServer\SchedulerPlugin\Status\SchedulerStatus;
use PHPStreamServer\MessageBus\MessageBusInterface;
use PHPStreamServer\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin;
use PHPStreamServer\Process;
use PHPStreamServer\Worker\LoggerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Revolt\EventLoop\Suspension;

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
        $this->scheduler = new Scheduler($this->status);
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
        $this->masterContainer->set(SchedulerStatus::class, $this->schedulerStatus);

        /** @var Suspension $suspension */
        $suspension = $this->masterContainer->get('suspension');
        /** @var LoggerInterface $logger */
        $logger = &$this->masterContainer->get('logger');
        /** @var MessageBusInterface $bus */
        $bus = &$this->masterContainer->get('bus');
        $this->handler = &$this->masterContainer->get('handler');

        $this->schedulerStatus->subscribeToWorkerMessages($this->handler);
        $this->scheduler->start($suspension, $logger, $bus);
    }

    public function afterStart(): void
    {
        if (\interface_exists(RegistryInterface::class)) {
            try {
                $registry = $this->masterContainer->get(RegistryInterface::class);
                $this->masterContainer->set('scheduler_metrics_handler', new MetricsHandler($registry, $this->schedulerStatus, $this->handler));
            } catch (NotFoundExceptionInterface) {}
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
