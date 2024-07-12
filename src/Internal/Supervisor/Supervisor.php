<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Supervisor;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\Relay\Relay;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connections;
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
    /** @var resource $workerPipeResource */
    private mixed $workerPipeResource;
    private Relay $workerRelay;

    public function __construct(private readonly int $stopTimeout)
    {
        $this->workerPool = new WorkerPool();
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->workerPool->addWorker($worker);
    }

    public function start(MasterProcess $masterProcess, Suspension $suspension): void
    {
        $this->logger = $masterProcess->logger;
        $this->suspension = $suspension;
        $this->serverStatus = $masterProcess->getServerStatus();
        $this->status = $masterProcess->getStatus(...);

        [$masterPipe, $this->workerPipeResource] = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->workerRelay = new Relay($masterPipe);
        $this->serverStatus->subscribeToWorkerMessages($this->workerRelay);

        SIGCHLDHandler::onChildProcessExit($this->onChildStop(...));
        EventLoop::repeat(WorkerProcess::HEARTBEAT_PERIOD, fn() => $this->monitorWorkerStatus());

        $this->spawnWorkers();
    }

    private function spawnWorkers(): void
    {
        EventLoop::defer(function (): void {
            foreach ($this->workerPool->getWorkers() as $worker) {
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

    /**
     * Runs in forked process
     */
    public function runWorker(WorkerProcess $worker): int
    {
        $this->free();

        return $worker->run($this->logger, new Relay($this->workerPipeResource));
    }

    private function free(): void
    {
        unset($this->workerPool, $this->suspension, $this->serverStatus, $this->workerRelay);
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    public function requestServerConnections(Relay $masterRelay): void
    {
        $pids = \iterator_to_array($this->workerPool->getAlivePids());
        $pids = \array_filter($pids, fn(int $pid) => !$this->serverStatus->isDetached($pid));
        \array_walk($pids, static fn(int $pid) => \posix_kill($pid, SIGUSR2));
        $connections = [];
        $counter = 0;
        $workerRelay = $this->workerRelay;
        $this->workerRelay->subscribe(
            Connections::class,
            $onReceive = static function (Connections $message) use (&$onReceive, &$connections, &$counter, &$pids, $masterRelay, $workerRelay) {
                \array_push($connections, ...$message->connections);
                if (++$counter === \count($pids)) {
                    $workerRelay->unsubscribe(Connections::class, $onReceive);
                    $masterRelay->publish(new Connections($connections));
                }
            },
        );
    }
}
