<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus;

use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Message\ConnectionClosedEvent;
use Luzrain\PHPStreamServer\Message\ConnectionCreatedEvent;
use Luzrain\PHPStreamServer\Message\ProcessBlockedEvent;
use Luzrain\PHPStreamServer\Message\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\Message\ProcessExitEvent;
use Luzrain\PHPStreamServer\Message\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\Message\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\Message\RequestCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Message\RxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Message\TxCounterIncreaseEvent;
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
     * @var array<int, WorkerInfo>
     */
    private array $workers = [];

    /**
     * @var array<int, PeriodicWorkerInfo>
     */
    private array $periodicWorkers = [];

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

        $handler->subscribe(ProcessExitEvent::class, weakClosure(function (ProcessExitEvent $message): void {
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
            $this->workers[$worker->getId()] = new WorkerInfo(
                user: $worker->getUser(),
                name: $worker->getName(),
                count: $worker->getProcessCount(),
            );
        } elseif($worker instanceof PeriodicProcessInterface) {
            $this->periodicWorkers[$worker->getId()] = new PeriodicWorkerInfo(
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
        return \count($this->workers);
    }

    /**
     * @return list<WorkerInfo>
     */
    public function getWorkers(): array
    {
        return \array_values($this->workers);
    }

    public function getPeriodicTasksCount(): int
    {
        return \count($this->periodicWorkers);
    }

    /**
     * @return list<PeriodicWorkerInfo>
     */
    public function getPeriodicWorkers(): array
    {
        return \array_values($this->periodicWorkers);
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
