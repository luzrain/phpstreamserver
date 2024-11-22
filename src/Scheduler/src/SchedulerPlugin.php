<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler;

use Amp\Future;
use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use PHPStreamServer\Core\Process;
use PHPStreamServer\Core\Worker\LoggerInterface;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;
use Psr\Container\NotFoundExceptionInterface;
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
