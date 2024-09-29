<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\Internal\Status;
use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Message\ProcessBlockedEvent;
use Luzrain\PHPStreamServer\Message\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\Message\ProcessExitEvent;
use Luzrain\PHPStreamServer\Message\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\WorkerProcessInterface;
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
    private DeferredFuture $stopFuture;

    public function __construct(
        private readonly MasterProcess $masterProcess,
        private readonly int $stopTimeout,
        private Status &$status,
    ) {
        $this->workerPool = new WorkerPool();
    }

    public function addWorker(WorkerProcessInterface $worker): void
    {
        $this->workerPool->registerWorker($worker);
    }

    public function start(Suspension $suspension): void
    {
        $this->suspension = $suspension;
        $this->logger = $this->masterProcess->getLogger();

        SIGCHLDHandler::onChildProcessExit(weakClosure($this->onChildStop(...)));
        EventLoop::repeat(WorkerProcessInterface::HEARTBEAT_PERIOD, weakClosure($this->monitorWorkerStatus(...)));

        $this->masterProcess->subscribe(ProcessDetachedEvent::class, function (ProcessDetachedEvent $message): void {
            $this->workerPool->markAsDetached($message->pid);
        });

        $this->masterProcess->subscribe(ProcessHeartbeatEvent::class, function (ProcessHeartbeatEvent $message): void {
            $this->workerPool->markAsHealthy($message->pid, $message->time);
        });

        $this->spawnWorkers();
    }

    private function spawnWorkers(): void
    {
        EventLoop::queue(function (): void {
            foreach ($this->workerPool->getRegisteredWorkers() as $worker) {
                while (\iterator_count($this->workerPool->getAliveWorkerPids($worker)) < $worker->getProcessCount()) {
                    if ($this->spawnWorker($worker)) {
                        return;
                    }
                }
            }
        });
    }

    private function spawnWorker(WorkerProcessInterface $worker): bool
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
        foreach ($this->workerPool->getProcesses() as $worker => $process) {
            $blockTime = $process->detached ? 0 : (int) \round((\hrtime(true) - $process->time) / 1000000000);
            if ($process->blocked === false && $blockTime > $this->workerPool::BLOCK_WARNING_TRESHOLD) {
                $this->workerPool->markAsBlocked($process->pid);
                EventLoop::defer(function () use ($process): void {
                    $this->masterProcess->dispatch(new ProcessBlockedEvent($process->pid));
                });
                $this->logger->warning(\sprintf(
                    'Worker %s[pid:%d] blocked event loop for more than %s seconds',
                    $worker->getName(),
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

        $this->workerPool->markAsDeleted($pid);

        EventLoop::defer(function () use ($pid, $exitCode): void {
            $this->masterProcess->dispatch(new ProcessExitEvent($pid, $exitCode));
        });

        switch ($this->status) {
            case Status::RUNNING:
                match ($exitCode) {
                    0 => $this->logger->info(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->getName(), $pid, $exitCode)),
                    $worker::RELOAD_EXIT_CODE => $this->logger->info(\sprintf('Worker %s[pid:%d] reloaded', $worker->getName(), $pid)),
                    default => $this->logger->warning(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->getName(), $pid, $exitCode)),
                };
                // Restart worker
                if (0 < $delay = $worker->getProcessRestartDelay()) {
                    EventLoop::delay($delay, function () use ($worker) { $this->spawnWorker($worker); });
                } else {
                    $this->spawnWorker($worker);
                }
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

        foreach ($this->workerPool->getProcesses() as $process) {
            \posix_kill($process->pid, SIGTERM);
        }

        if ($this->workerPool->getWorkerCount() === 0) {
            $this->stopFuture->complete();
        } else {
            $stopCallbackId = EventLoop::delay($this->stopTimeout, function (): void {
                // Send SIGKILL signal to all child processes ater timeout
                foreach ($this->workerPool->getProcesses() as $worker => $process) {
                    \posix_kill($process->pid, SIGKILL);
                    $this->logger->notice(\sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->getName(), $process->pid, $this->stopTimeout));
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
        foreach ($this->workerPool->getProcesses() as $process) {
            \posix_kill($process->pid, $process->detached ? SIGTERM : SIGUSR1);
        }
    }
}
