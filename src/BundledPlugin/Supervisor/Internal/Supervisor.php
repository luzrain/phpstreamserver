<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessBlockedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessExitEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessSetOptionsEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\WorkerProcess;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\SIGCHLDHandler;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Status;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use function Amp\weakClosure;

/**
 * @internal
 */
final class Supervisor
{
    public MessageHandlerInterface $messageHandler;
    public MessageBusInterface $messageBus;
    private WorkerPool $workerPool;
    private LoggerInterface $logger;
    private Suspension $suspension;
    private DeferredFuture $stopFuture;

    public function __construct(
        private Status &$status,
        private readonly int $stopTimeout,
        private readonly float $restartDelay,
    ) {
        $this->workerPool = new WorkerPool();
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->workerPool->registerWorker($worker);
    }

    public function start(Suspension $suspension, LoggerInterface $logger, MessageHandlerInterface $messageHandler, MessageBusInterface $messageBus): void
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

        EventLoop::repeat(WorkerProcess::HEARTBEAT_PERIOD, weakClosure($this->monitorWorkerStatus(...)));

        $this->messageHandler->subscribe(ProcessDetachedEvent::class, weakClosure(function (ProcessDetachedEvent $message): void {
            $this->workerPool->markAsDetached($message->pid);
        }));

        $this->messageHandler->subscribe(ProcessHeartbeatEvent::class, weakClosure(function (ProcessHeartbeatEvent $message): void {
            $this->workerPool->markAsHealthy($message->pid, $message->time);
        }));

        $this->messageHandler->subscribe(ProcessSetOptionsEvent::class, weakClosure(function (ProcessSetOptionsEvent $message): void {
            $this->workerPool->setReloadable($message->pid, $message->reloadable);
        }));

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
                    $worker->name,
                    $process->pid,
                    $blockTime,
                ));
            }
        }
    }

    private function onWorkerStart(WorkerProcess $worker, int $pid): void
    {
        $this->workerPool->addChild($worker, $pid);
    }

    private function onWorkerStop(WorkerProcess $worker, int $pid, int $exitCode): void
    {
        $this->workerPool->markAsDeleted($pid);

        EventLoop::defer(function () use ($pid, $exitCode): void {
            $this->messageBus->dispatch(new ProcessExitEvent($pid, $exitCode));
        });

        if ($this->status === Status::RUNNING) {
            if ($exitCode === 0) {
                $this->logger->info(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->name, $pid, $exitCode));
            } elseif($exitCode === $worker::RELOAD_EXIT_CODE && $worker->reloadable) {
                $this->logger->info(\sprintf('Worker %s[pid:%d] reloaded', $worker->name, $pid));
            } else {
                $this->logger->warning(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->name, $pid, $exitCode));
            }

            // Restart worker
            EventLoop::delay(\max($this->restartDelay, 0), function () use($worker): void {
                $this->spawnWorker($worker);
            });
        } else {
            if ($this->workerPool->getProcessesCount() === 0) {
                // All processes are stopped now
                $this->stopFuture->complete();
            }
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
                    $this->logger->notice(\sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->name, $process->pid, $this->stopTimeout));
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
            if ($process->reloadable) {
                \posix_kill($process->pid, $process->detached ? SIGTERM : SIGUSR1);
            }
        }
    }
}
