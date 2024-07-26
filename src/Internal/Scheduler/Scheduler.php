<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Scheduler;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\Scheduler\Trigger\TriggerFactory;
use Luzrain\PHPStreamServer\Internal\Scheduler\Trigger\TriggerInterface;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class Scheduler
{
    private LoggerInterface $logger;
    private Suspension $suspension;
    private WorkerPool $pool;
    private DeferredFuture|null $stopFuture = null;

    public function __construct()
    {
        $this->pool = new WorkerPool();
    }

    public function addWorker(PeriodicProcess $worker): void
    {
        $this->pool->addWorker($worker);
    }

    public function start(MasterProcess $masterProcess, Suspension $suspension): void
    {
        $this->logger = $masterProcess->logger;
        $this->suspension = $suspension;

        SIGCHLDHandler::onChildProcessExit($this->onChildStop(...));

        foreach ($this->pool->getWorkers() as $worker) {
            try {
                $trigger = TriggerFactory::create($worker->schedule, $worker->jitter);
            } catch (\InvalidArgumentException) {
                $this->logger->warning(\sprintf('Periodic task "%s" skipped. Schedule "%s" is not in valid format', $worker->name, $worker->schedule));
                continue;
            }

            $this->scheduleWorker($worker, $trigger);
        }
    }

    private function scheduleWorker(PeriodicProcess $worker, TriggerInterface $trigger): void
    {
        $currentDate = new \DateTimeImmutable();
        $nextRunDate = $trigger->getNextRunDate($currentDate);
        if ($nextRunDate !== null) {
            $delay = $nextRunDate->getTimestamp() - $currentDate->getTimestamp();
            EventLoop::delay($delay, fn() => $this->startWorker($worker, $trigger));
        }
    }

    private function startWorker(PeriodicProcess $worker, TriggerInterface $trigger): void
    {
        // Reschedule a task without running it if the previous task is still running
        if ($this->pool->isWorkerRun($worker)) {
            $this->logger->info(\sprintf('Periodic task "%s" is already running. Rescheduled', $worker->name));
            $this->scheduleWorker($worker, $trigger);
            return;
        }

        // Spawn process
        if (0 === $pid = $this->spawnWorker($worker)) {
            return;
        }

        $this->logger->info(\sprintf('Periodic task "%s" [pid:%s] started', $worker->name, $pid));
        $this->scheduleWorker($worker, $trigger);
    }

    private function spawnWorker(PeriodicProcess $worker): int
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
        $this->logger->info(\sprintf('Periodic task "%s" [pid:%s] exit with code %s', $worker->name, $pid, $exitCode));

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

    private function free(): void
    {
        unset($this->suspension);
        unset($this->pool);
        unset($this->stopFuture);
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    /**
     * Runs in forked process
     */
    public function runWorker(PeriodicProcess $worker): int
    {
        $this->free();

        return $worker->run($this->logger);
    }
}
