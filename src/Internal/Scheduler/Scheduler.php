<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Scheduler;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
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



        EventLoop::delay(1, function () {
            foreach ($this->pool->getWorkers() as $worker) {
                $this->spawnWorker($worker);
            }
        });
    }

    private function spawnWorker(PeriodicProcess $worker): bool
    {
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process
            $this->pool->addChild($worker, $pid);
            $this->logger->info(\sprintf('Periodic task "%s" [pid:%s] started', $worker->name, $pid));
            return false;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return true;
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
        $this->logger->info(\sprintf('Periodic task "%s" [pid:%s] stopped', $worker->name, $pid));

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
        unset($this->suspension, $this->pool, $this->stopFuture);
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    /**
     * Runs in forked process
     */
    public function runWorker(PeriodicProcess $worker): int
    {
        $this->free();

        return $worker->run();
    }
}
