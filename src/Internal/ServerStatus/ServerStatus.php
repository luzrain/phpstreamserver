<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\Message\ProcessBlockedEvent;
use Luzrain\PHPStreamServer\Internal\Message\ConnectionCreatedEvent;
use Luzrain\PHPStreamServer\Internal\Message\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\Internal\Message\ConnectionClosedEvent;
use Luzrain\PHPStreamServer\Internal\Message\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\Internal\Message\ProcessExitedEvent;
use Luzrain\PHPStreamServer\Internal\Message\RequestCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Internal\Message\RxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Internal\Message\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\Internal\Message\TxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\PeriodicProcessInterface;
use Luzrain\PHPStreamServer\ProcessInterface;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcessInterface;
use Revolt\EventLoop\DriverFactory;
use function Amp\weakClosure;

/**
 * @readonly
 * @psalm-allow-private-mutation
 */
final class ServerStatus
{
    public readonly string $version;
    public readonly string $phpVersion;
    public readonly string $eventLoop;
    public readonly string $startFile;
    public \DateTimeImmutable|null $startedAt;
    public bool $isRunning;

    /**
     * @var array<int, WorkerProcessInfo>
     */
    private array $workerProcesses = [];

    /**
     * @var array<int, PeriodicProcessInfo>
     */
    private array $periodicProcesses = [];

    /**
     * @var array<int, RunningProcess>
     */
    private array $processes = [];

    public function __construct()
    {
        $this->version = Server::VERSION;
        $this->phpVersion = PHP_VERSION;
        $this->eventLoop = (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
        $this->startFile = Functions::getStartFile();
        $this->startedAt = null;
        $this->isRunning = false;
    }

    public function subscribeToWorkerMessages(MessageHandler $handler): void
    {
        $handler->subscribe(ProcessSpawnedEvent::class, weakClosure(function (ProcessSpawnedEvent $message): void {
            $this->processes[$message->pid] = new RunningProcess(
                pid: $message->pid,
                user: $message->user,
                name: $message->name,
                startedAt: $message->startedAt,
            );
        }));

        $handler->subscribe(ProcessHeartbeatEvent::class, weakClosure(function (ProcessHeartbeatEvent $message): void {
            if (!isset($this->processes[$message->pid]) || $this->processes[$message->pid]?->detached === true) {
                return;
            }

            $this->processes[$message->pid]->memory = $message->memory;
            $this->processes[$message->pid]->blocked = false;
        }));

        $handler->subscribe(ProcessBlockedEvent::class, weakClosure(function (ProcessBlockedEvent $message): void {
            if (!isset($this->processes[$message->pid]) || $this->processes[$message->pid]?->detached === true) {
                return;
            }

            $this->processes[$message->pid]->blocked = true;
        }));

        $handler->subscribe(ProcessExitedEvent::class, weakClosure(function (ProcessExitedEvent $message): void {
            unset($this->processes[$message->pid]);
        }));

        $handler->subscribe(ProcessDetachedEvent::class, weakClosure(function (ProcessDetachedEvent $message): void {
            if (!isset($this->processes[$message->pid])) {
                return;
            }

            $this->processes[$message->pid]->detached = true;
            $this->processes[$message->pid]->memory = 0;
            $this->processes[$message->pid]->requests = 0;
            $this->processes[$message->pid]->rx = 0;
            $this->processes[$message->pid]->tx = 0;
            $this->processes[$message->pid]->blocked = false;
            $this->processes[$message->pid]->connections = [];
        }));

        $handler->subscribe(RxCounterIncreaseEvent::class, weakClosure(function (RxCounterIncreaseEvent $message): void {
            $this->processes[$message->pid]->connections[$message->connectionId]->rx += $message->rx;
            $this->processes[$message->pid]->rx += $message->rx;
        }));

        $handler->subscribe(TxCounterIncreaseEvent::class, weakClosure(function (TxCounterIncreaseEvent $message): void {
            $this->processes[$message->pid]->connections[$message->connectionId]->tx += $message->tx;
            $this->processes[$message->pid]->tx += $message->tx;
        }));

        $handler->subscribe(RequestCounterIncreaseEvent::class, weakClosure(function (RequestCounterIncreaseEvent $message): void {
            $this->processes[$message->pid]->requests += $message->requests;
        }));

        $handler->subscribe(ConnectionCreatedEvent::class, weakClosure(function (ConnectionCreatedEvent $message): void {
            $this->processes[$message->pid]->connections[$message->connectionId] = $message->connection;
        }));

        $handler->subscribe(ConnectionClosedEvent::class, weakClosure(function (ConnectionClosedEvent $message): void {
            unset($this->processes[$message->pid]->connections[$message->connectionId]);
        }));
    }

    public function addWorker(ProcessInterface $worker): void
    {
        if ($worker instanceof WorkerProcessInterface) {
            $this->workerProcesses[$worker->getId()] = new WorkerProcessInfo(
                user: $worker->getUser(),
                name: $worker->getName(),
                count: $worker->getProcessCount(),
            );
        } elseif($worker instanceof PeriodicProcessInterface) {
            $this->periodicProcesses[$worker->getId()] = new PeriodicProcessInfo(
                user: $worker->getUser(),
                name: $worker->getName(),
            );
        }
    }

    public function setRunning(bool $isRunning = true): void
    {
        $this->startedAt = $isRunning ? new \DateTimeImmutable('now') : null;
        $this->isRunning = $isRunning;
    }

    public function getWorkersCount(): int
    {
        return \count($this->workerProcesses);
    }

    /**
     * @return list<WorkerProcessInfo>
     */
    public function getWorkerProcesses(): array
    {
        return \array_values($this->workerProcesses);
    }

    public function getPeriodicTasksCount(): int
    {
        return \count($this->periodicProcesses);
    }

    /**
     * @return list<PeriodicProcessInfo>
     */
    public function getPeriodicProcesses(): array
    {
        return \array_values($this->periodicProcesses);
    }

    public function getProcessesCount(): int
    {
        return \count($this->processes);
    }

    /**
     * @return list<RunningProcess>
     */
    public function getProcesses(): array
    {
        return \array_values($this->processes);
    }

    public function getTotalMemory(): int
    {
        return (int) \array_sum(\array_map(static fn(RunningProcess $p): int => $p->memory, $this->processes));
    }

    public function getTotalConnections(): int
    {
        return (int) \array_sum(\array_map(static fn(RunningProcess $p): array => $p->connections, $this->processes));
    }
}
