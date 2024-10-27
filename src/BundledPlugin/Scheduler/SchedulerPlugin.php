<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Command\SchedulerCommand;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Internal\SchedulerStatus;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\Scheduler\Scheduler;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\Process;
use Revolt\EventLoop\Suspension;

final class SchedulerPlugin extends Plugin
{
    private SchedulerStatus $schedulerStatus;
    private Container $masterContainer;
    private Scheduler $scheduler;

    public function __construct()
    {
    }

    public function workerSupports(): array
    {
        return [PeriodicProcess::class];
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterContainer = $masterProcess->masterContainer;
        $this->scheduler = new Scheduler($masterProcess->status);
        $this->schedulerStatus = new SchedulerStatus();
        $this->masterContainer->set(SchedulerStatus::class, $this->schedulerStatus);
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof PeriodicProcess);
        $this->scheduler->addWorker($worker);
        $this->schedulerStatus->addWorker($worker);
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

        $this->schedulerStatus->subscribeToWorkerMessages($handler);
        $this->scheduler->start($suspension, $logger, $bus);
        //$this->pcntlExec = \is_string($this->command) ? PcntlExecCommandConverter::convert($this->command) : null;
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
