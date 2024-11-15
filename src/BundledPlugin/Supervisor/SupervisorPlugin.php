<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\RegistryInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\MetricsHandler;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\Supervisor;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\LoggerInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Plugin;
use Luzrain\PHPStreamServer\Process;
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
