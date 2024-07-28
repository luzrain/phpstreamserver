<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\Internal\Status;
use Luzrain\PHPStreamServer\Internal\WorkerProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use function Amp\weakClosure;

/**
 * @internal
 */
final class Supervisor
{
    private WorkerPool $workerPool;
    private LoggerInterface $logger;
    private Suspension $suspension;
    /** @var \Closure(): Status */
    private \Closure $status;
    private ServerStatus $serverStatus;
    private DeferredFuture $stopFuture;

    public function __construct(
        private readonly int $stopTimeout,
    ) {
        $this->workerPool = new WorkerPool();
    }

    public function registerWorkerProcess(WorkerProcess $worker): void
    {
        $this->workerPool->registerWorkerProcess($worker);
    }

    /**
     * @param \Closure(): Status $status
     */
    public function start(
        LoggerInterface $logger,
        Suspension $suspension,
        \Closure $status,
        ServerStatus $serverStatus,
    ): void {
        $this->logger = $logger;
        $this->suspension = $suspension;
        $this->serverStatus = $serverStatus;
        $this->status = $status;

        SIGCHLDHandler::onChildProcessExit(weakClosure($this->onChildStop(...)));
        EventLoop::repeat(WorkerProcess::HEARTBEAT_PERIOD, weakClosure($this->monitorWorkerStatus(...)));

        $this->spawnWorkers();
    }

    private function spawnWorkers(): void
    {
        EventLoop::queue(function (): void {
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
            $this->suspension->resume($worker);
            return true;
        } else {
            throw new PHPStreamServerException('fork fail');
        }
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
            $stopCallbackId = EventLoop::delay($this->stopTimeout, function (): void {
                // Send SIGKILL signal to all child processes ater timeout
                foreach ($this->workerPool->getAlivePids() as $pid) {
                    \posix_kill($pid, SIGKILL);
                    $worker = $this->workerPool->getWorkerByPid($pid);
                    $this->logger->notice(\sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->name, $pid, $this->stopTimeout));
                }
                $this->stopFuture->complete();
            });

            $this->stopFuture->getFuture()->finally(static function () use ($stopCallbackId) {
                EventLoop::cancel($stopCallbackId);
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
}
