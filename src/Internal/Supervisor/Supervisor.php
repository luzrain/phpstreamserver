<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\Internal\Status;
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
    public MessageHandler $messageHandler;
    public MessageBus $messageBus;
    private WorkerPool $workerPool;
    private LoggerInterface $logger;
    private Suspension $suspension;
    private DeferredFuture $stopFuture;

    public function __construct(
        private Status &$status,
        private readonly int $stopTimeout,
    ) {
        $this->workerPool = new WorkerPool();
    }

    public function addWorker(WorkerProcessInterface $worker): void
    {
        $this->workerPool->registerWorker($worker);
    }

    public function start(Suspension $suspension, LoggerInterface $logger, MessageHandler $messageHandler, MessageBus $messageBus): void
    {
        $this->suspension = $suspension;
        $this->logger = $logger;
        $this->messageHandler = $messageHandler;
        $this->messageBus = $messageBus;

        SIGCHLDHandler::onChildProcessExit(weakClosure(function (int $pid, int $exitCode) {
            if (null !== $worker = $this->workerPool->getWorkerByPid($pid)) {
                $this->onWorkerStop($worker, $pid, $exitCode);
            }
        }));

        EventLoop::repeat(WorkerProcessInterface::HEARTBEAT_PERIOD, weakClosure($this->monitorWorkerStatus(...)));

        $this->messageHandler->subscribe(ProcessDetachedEvent::class, weakClosure(function (ProcessDetachedEvent $message): void {
            $this->workerPool->markAsDetached($message->pid);
        }));

        $this->messageHandler->subscribe(ProcessHeartbeatEvent::class, weakClosure(function (ProcessHeartbeatEvent $message): void {
            $this->workerPool->markAsHealthy($message->pid, $message->time);
        }));

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
            $this->onWorkerStart($worker, $pid);
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
                    $this->messageBus->dispatch(new ProcessBlockedEvent($process->pid));
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

    private function onWorkerStart(WorkerProcessInterface $worker, int $pid): void
    {
        $this->workerPool->addChild($worker, $pid);
    }

    private function onWorkerStop(WorkerProcessInterface $worker, int $pid, int $exitCode): void
    {
        $this->workerPool->markAsDeleted($pid);

        EventLoop::defer(function () use ($pid, $exitCode): void {
            $this->messageBus->dispatch(new ProcessExitEvent($pid, $exitCode));
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
