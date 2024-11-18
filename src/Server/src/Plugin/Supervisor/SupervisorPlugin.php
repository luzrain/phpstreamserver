<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Supervisor;

use Amp\Future;
use PHPStreamServer\BundledPlugin\Metrics\RegistryInterface;
use PHPStreamServer\MessageBus\MessageBusInterface;
use PHPStreamServer\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin\Plugin;
use PHPStreamServer\Plugin\Supervisor\Command\ProcessesCommand;
use PHPStreamServer\Plugin\Supervisor\Internal\MetricsHandler;
use PHPStreamServer\Plugin\Supervisor\Internal\Supervisor;
use PHPStreamServer\Plugin\Supervisor\Status\SupervisorStatus;
use PHPStreamServer\Process;
use PHPStreamServer\Worker\LoggerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Revolt\EventLoop\Suspension;

final class SupervisorPlugin extends Plugin
{
    private SupervisorStatus $supervisorStatus;
    private Supervisor $supervisor;
    private MessageHandlerInterface $handler;
    private MessageBusInterface $bus;

    public function __construct(
        private readonly int $stopTimeout,
        private readonly float $restartDelay,
    ) {
    }

    protected function beforeStart(): void
    {
        $this->supervisor = new Supervisor($this->status, $this->stopTimeout, $this->restartDelay);
        $this->supervisorStatus = new SupervisorStatus();
        $this->masterContainer->set(SupervisorStatus::class, $this->supervisorStatus);
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof WorkerProcess);
        $this->supervisor->addWorker($worker);
        $this->supervisorStatus->addWorker($worker);
    }

    public function onStart(): void
    {
        /** @var Suspension $suspension */
        $suspension = &$this->masterContainer->get('suspension');
        /** @var LoggerInterface $logger */
        $logger = &$this->masterContainer->get('logger');
        $this->handler = &$this->masterContainer->get('handler');
        $this->bus = &$this->masterContainer->get('bus');

        $this->supervisorStatus->subscribeToWorkerMessages($this->handler);
        $this->supervisor->start($suspension, $logger, $this->handler, $this->bus);
    }

    public function afterStart(): void
    {
        if (\interface_exists(RegistryInterface::class)) {
            try {
                $registry = $this->masterContainer->get(RegistryInterface::class);
                $this->masterContainer->set('supervisor_metrics_handler', new MetricsHandler($registry, $this->supervisorStatus, $this->handler));
            } catch (NotFoundExceptionInterface) {}
        }
    }

    public function onStop(): Future
    {
        return $this->supervisor->stop();
    }

    public function onReload(): void
    {
        $this->supervisor->reload();
    }

    public function registerCommands(): iterable
    {
        return [
            new ProcessesCommand(),
        ];
    }
}
