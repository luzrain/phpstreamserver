<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\Internal\Status;
use Luzrain\PHPStreamServer\WorkerProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

/**
 * @internal
 */
final class Supervisor
{
    private WorkerPool $workerPool;
    private Suspension $suspension;
    private ServerStatus $serverStatus;
    private readonly LoggerInterface $logger;
    /** @var \Closure(): Status */
    private \Closure $status;
    private DeferredFuture $stopFuture;

    private readonly MessageHandler $messageHandler;
    private readonly string $socketFile;

    public function __construct(
        private readonly int $stopTimeout,
    ) {
        $this->workerPool = new WorkerPool();
    }

    public function addWorkerProcess(WorkerProcess $worker): void
    {
        $this->workerPool->registerWorkerProcess($worker);
    }

    public function start(MasterProcess $masterProcess, Suspension $suspension): void
    {
        $this->logger = $masterProcess->logger;
        $this->suspension = $suspension;
        $this->serverStatus = $masterProcess->getServerStatus();
        $this->status = $masterProcess->getStatus(...);
        $this->serverStatus->subscribeToWorkerMessages($this->messageHandler);

        SIGCHLDHandler::onChildProcessExit($this->onChildStop(...));
        EventLoop::repeat(WorkerProcess::HEARTBEAT_PERIOD, fn() => $this->monitorWorkerStatus());

        $this->spawnWorkers();
    }

    private function spawnWorkers(): void
    {
        EventLoop::defer(function (): void {
            foreach ($this->workerPool->getRegisteredWorkers() as $worker) {
                while (\iterator_count($this->workerPool->getAliveWorkerPids($worker)) < $worker->count) {
                    if ($this->spawnWorker($worker)) {
                        return;
                    }
                }
            }
        });
    }

    private function spawnWorker(WorkerProcess $worker): bool
    {
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process
            $this->workerPool->addChild($worker, $pid);
            return false;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker->setPipe($this->socketFile));
            return true;
        } else {
            throw new PHPStreamServerException('fork fail');
        }
    }

    /**
     * Runs in forked process
     */
    public function runWorker(WorkerProcess $worker): int
    {
        $this->free();

        return $worker->run($this->logger);
    }

    private function monitorWorkerStatus(): void
    {
        foreach ($this->serverStatus->getProcesses() as $process) {
            $blockTime = $process->detached ? 0 : (int) \round((\hrtime(true) - $process->time) / 1000000000);
            if ($process->blocked === false && $blockTime > $this->serverStatus::BLOCK_WARNING_TRESHOLD) {
                $this->serverStatus->markProcessAsBlocked($process->pid);
                $this->logger->warning(\sprintf(
                    'Worker %s[pid:%d] blocked event loop for more than %s seconds',
                    $this->workerPool->getWorkerByPid($process->pid)->name,
                    $process->pid,
                    $blockTime,
                ));
            }
        }
    }

    private function onChildStop(int $pid, int $exitCode): void
    {
        if (null === $worker = $this->workerPool->getWorkerByPid($pid)) {
            return;
        }

        $this->workerPool->deleteChild($pid);
        $this->serverStatus->deleteProcess($pid);

        switch (($this->status)()) {
            case Status::RUNNING:
                match ($exitCode) {
                    0 => $this->logger->info(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->name, $pid, $exitCode)),
                    $worker::RELOAD_EXIT_CODE => $this->logger->info(\sprintf('Worker %s[pid:%d] reloaded', $worker->name, $pid)),
                    default => $this->logger->warning(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->name, $pid, $exitCode)),
                };
                // Restart worker
                $this->spawnWorker($worker);
                break;
            case Status::SHUTDOWN:
                if ($this->workerPool->getProcessesCount() === 0) {
                    // All processes are stopped now
                    $this->stopFuture->complete();
                }
                break;
        }
    }

    public function stop(): Future
    {
        $this->stopFuture = new DeferredFuture();

        foreach ($this->workerPool->getAlivePids() as $pid) {
            \posix_kill($pid, SIGTERM);
        }

        if ($this->workerPool->getWorkerCount() === 0) {
            $this->stopFuture->complete();
        } else {
            EventLoop::delay($this->stopTimeout, function (): void {
                if ($this->stopFuture->isComplete()) {
                    return;
                }

                // Send SIGKILL signal to all child processes ater timeout
                foreach ($this->workerPool->getAlivePids() as $pid) {
                    \posix_kill($pid, SIGKILL);
                    $worker = $this->workerPool->getWorkerByPid($pid);
                    $this->logger->notice(\sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->name, $pid, $this->stopTimeout));
                }
                $this->stopFuture->complete();
            });
        }

        return $this->stopFuture->getFuture();
    }

    public function reload(): void
    {
        foreach ($this->workerPool->getAlivePids() as $pid) {
            \posix_kill($pid, $this->serverStatus->isDetached($pid) ? SIGTERM : SIGUSR1);
        }
    }

    private function free(): void
    {
        unset($this->workerPool);
        unset($this->suspension);
        unset($this->serverStatus);
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    public function setHandler(MessageHandler $messageHandler, string $socketFile): void
    {
        $this->messageHandler = $messageHandler;
        $this->socketFile = $socketFile;
    }
}
