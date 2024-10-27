<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\Supervisor\Supervisor;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\Process;
use Revolt\EventLoop\Suspension;

final class SupervisorPlugin extends Plugin
{
    private SupervisorStatus $supervisorStatus;
    private Container $masterContainer;
    private Supervisor $supervisor;

    public function __construct(
        private readonly int $stopTimeout = 10,
        private readonly float $restartDelay = 0.25,
    ) {
    }

    public function workerSupports(): array
    {
        return [WorkerProcess::class];
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterContainer = $masterProcess->masterContainer;
        $this->supervisor = new Supervisor($masterProcess->status, $this->stopTimeout, $this->restartDelay);
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
        /** @var MessageBus $bus */
        $bus = &$this->masterContainer->get('bus');
        /** @var MessageHandler $handler */
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