<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\Supervisor;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\LoggerInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Plugin;
use Luzrain\PHPStreamServer\Process;
use Revolt\EventLoop\Suspension;

final class SupervisorPlugin extends Plugin
{
    private SupervisorStatus $supervisorStatus;
    private Supervisor $supervisor;

    public function __construct(
        private readonly int $stopTimeout,
        private readonly float $restartDelay,
    ) {
    }

    public function init(): void
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

    public function start(): void
    {
        /** @var Suspension $suspension */
        $suspension = $this->masterContainer->get('suspension');
        /** @var LoggerInterface $logger */
        $logger = &$this->masterContainer->get('logger');
        /** @var MessageBusInterface $bus */
        $bus = &$this->masterContainer->get('bus');
        /** @var MessageHandlerInterface $handler */
        $handler = &$this->masterContainer->get('handler');

        $this->supervisorStatus->subscribeToWorkerMessages($handler);
        $this->supervisor->start($suspension, $logger, $handler, $bus);
    }

    public function stop(): Future
    {
        return $this->supervisor->stop();
    }

    public function reload(): void
    {
        $this->supervisor->reload();
    }

    public function commands(): iterable
    {
        return [
            new ProcessesCommand(),
        ];
    }
}
