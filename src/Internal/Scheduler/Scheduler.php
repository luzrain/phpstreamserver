<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Scheduler;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\Scheduler\Trigger\TriggerFactory;
use Luzrain\PHPStreamServer\Internal\Scheduler\Trigger\TriggerInterface;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\Internal\Status;
use Luzrain\PHPStreamServer\Message\ProcessScheduledEvent;
use Luzrain\PHPStreamServer\PeriodicProcessInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use function Amp\weakClosure;

/**
 * @internal
 */
final class Scheduler
{
    private MessageBus $messageBus;
    private WorkerPool $pool;
    private LoggerInterface $logger;
    private Suspension $suspension;
    private DeferredFuture|null $stopFuture = null;

    public function __construct(
        private Status &$status,
    ) {
        $this->pool = new WorkerPool();
    }

    public function addWorker(PeriodicProcessInterface $worker): void
    {
        $this->pool->addWorker($worker);
    }

    public function start(Suspension $suspension, LoggerInterface $logger, MessageBus $messageBus): void
    {
        $this->suspension = $suspension;
        $this->logger = $logger;
        $this->messageBus = $messageBus;

        SIGCHLDHandler::onChildProcessExit(weakClosure($this->onChildStop(...)));

        foreach ($this->pool->getWorkers() as $worker) {
            /** @var PeriodicProcessInterface $worker */
            try {
                $trigger = TriggerFactory::create($worker->getSchedule(), $worker->getJitter());
            } catch (\InvalidArgumentException) {
                $this->logger->warning(\sprintf('Periodic process "%s" skipped. Schedule "%s" is not in valid format', $worker->getName(), $worker->getSchedule()));
                continue;
            }

            $this->scheduleWorker($worker, $trigger);
        }
    }

    private function scheduleWorker(PeriodicProcessInterface $worker, TriggerInterface $trigger): bool
    {
        if ($this->status === Status::SHUTDOWN) {
            return false;
        }

        $currentDate = new \DateTimeImmutable();
        $nextRunDate = $trigger->getNextRunDate($currentDate);

        if ($nextRunDate !== null) {
            $delay = $nextRunDate->getTimestamp() - $currentDate->getTimestamp();
            EventLoop::delay($delay, function () use($worker, $trigger): void {
                $this->startWorker($worker, $trigger);
            });
        }

        EventLoop::defer(function () use ($worker, $nextRunDate): void {
            $this->messageBus->dispatch(new ProcessScheduledEvent($worker->getId(), $nextRunDate));
        });

        return true;
    }

    private function startWorker(PeriodicProcessInterface $worker, TriggerInterface $trigger): void
    {
        // Reschedule a task without running it if the previous task is still running
        if ($this->pool->isWorkerRun($worker)) {
            if($this->scheduleWorker($worker, $trigger)) {
                $this->logger->info(\sprintf('Periodic process "%s" is already running. Rescheduled', $worker->getName()));
            }
            return;
        }

        // Spawn process
        if (0 === $pid = $this->spawnWorker($worker)) {
            return;
        }

        $this->logger->info(\sprintf('Periodic process "%s" [pid:%s] started', $worker->getName(), $pid));
        $this->scheduleWorker($worker, $trigger);
    }

    private function spawnWorker(PeriodicProcessInterface $worker): int
    {
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process
            $this->pool->addChild($worker, $pid);
            return $pid;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return 0;
        } else {
            throw new PHPStreamServerException('fork fail');
        }
    }

    private function onChildStop(int $pid, int $exitCode): void
    {
        if (null === $worker = $this->pool->getWorkerByPid($pid)) {
            return;
        }

        $this->pool->deleteChild($worker);
        $this->logger->info(\sprintf('Periodic process "%s" [pid:%s] exit with code %s', $worker->getName(), $pid, $exitCode));

        if ($this->stopFuture !== null && !$this->stopFuture->isComplete() && $this->pool->getProcessesCount() === 0) {
            $this->stopFuture->complete();
        }
    }

    public function stop(): Future
    {
        $this->stopFuture = new DeferredFuture();

        if ($this->pool->getProcessesCount() === 0) {
            $this->stopFuture->complete();
        }

        return $this->stopFuture->getFuture();
    }
}
