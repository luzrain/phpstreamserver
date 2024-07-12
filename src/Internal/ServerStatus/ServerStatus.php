<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\Relay\Relay;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connect;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Disconnect;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\RequestInc;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\RxtInc;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Spawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\TxtInc;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcess;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;
use function Amp\weakClosure;

final class ServerStatus
{
    private const BLOCKED_LABEL_PERSISTENCE = 30;
    public const BLOCK_WARNING_TRESHOLD = 6;

    public readonly string $version;
    public readonly string $phpVersion;
    public readonly string $eventLoop;
    public readonly string $startFile;
    public \DateTimeImmutable|null $startedAt;
    public bool $isRunning;

    /**
     * @var array<int, Worker>
     */
    private array $workers = [];

    /**
     * @var array<int, PeriodicTask>
     */
    private array $periodicTasks = [];

    /**
     * @var array<int, Process>
     */
    private array $processes = [];

    /**
     * @var array<int, true>
     */
    private array $blockedProcesses = [];

    public function __construct()
    {
        $this->version = Server::VERSION;
        $this->phpVersion = PHP_VERSION;
        $this->eventLoop = (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
        $this->startFile = Functions::getStartFile();
        $this->startedAt = null;
        $this->isRunning = false;
    }

    public function subscribeToWorkerMessages(Relay $relay): void
    {
        $relay->subscribe(Spawn::class, weakClosure(function (Spawn $message) {
            $this->processes[$message->pid] = new Process(
                pid: $message->pid,
                user: $message->user,
                name: $message->name,
                startedAt: $message->startedAt,
            );
        }));

        $relay->subscribe(Heartbeat::class, weakClosure(function (Heartbeat $message) {
            $this->processes[$message->pid]->memory = $message->memory;
            $this->processes[$message->pid]->time = $message->time;

            if (!isset($this->blockedProcesses[$message->pid])) {
                $this->processes[$message->pid]->blocked = false;
            }
        }));

        $relay->subscribe(Detach::class, weakClosure(function (Detach $message) {
            $this->processes[$message->pid]->detached = true;
            $this->processes[$message->pid]->memory = 0;
            $this->processes[$message->pid]->requests = 0;
            $this->processes[$message->pid]->rx = 0;
            $this->processes[$message->pid]->tx = 0;
            $this->processes[$message->pid]->connections = 0;
            $this->processes[$message->pid]->time = 0;
            $this->processes[$message->pid]->blocked = false;
        }));

        $relay->subscribe(RxtInc::class, weakClosure(function (RxtInc $message) {
            $this->processes[$message->pid]->rx += $message->rx;
        }));

        $relay->subscribe(TxtInc::class, weakClosure(function (TxtInc $message) {
            $this->processes[$message->pid]->tx += $message->tx;
        }));

        $relay->subscribe(RequestInc::class, weakClosure(function (RequestInc $message) {
            $this->processes[$message->pid]->requests += $message->requests;
        }));

        $relay->subscribe(Connect::class, weakClosure(function (Connect $message) {
            $this->processes[$message->pid]->connections++;
        }));

        $relay->subscribe(Disconnect::class, weakClosure(function (Disconnect $message) {
            $this->processes[$message->pid]->connections--;
        }));
    }

    public function addWorker(WorkerProcess $worker): void
    {
        $this->workers[$worker->id] = new Worker(
            user: $worker->getUser(),
            name: $worker->name,
            count: $worker->count,
        );
    }

    public function addPeriodicTask(PeriodicProcess $worker): void
    {
        $this->periodicTasks[$worker->id] = new PeriodicTask(
            user: $worker->getUser(),
            name: $worker->name,
        );
    }

    public function setRunning(bool $isRunning): void
    {
        $this->startedAt = $isRunning ? new \DateTimeImmutable('now') : null;
        $this->isRunning = $isRunning;
    }

    public function deleteProcess(int $pid): void
    {
        unset($this->processes[$pid]);
    }

    public function markProcessAsBlocked(int $pid): void
    {
        $this->blockedProcesses[$pid] = true;
        $this->processes[$pid]->blocked = true;

        EventLoop::delay(self::BLOCKED_LABEL_PERSISTENCE, function () use ($pid) {
            unset($this->blockedProcesses[$pid]);
        });
    }

    public function getWorkersCount(): int
    {
        return \count($this->workers);
    }

    /**
     * @return list<Worker>
     */
    public function getWorkers(): array
    {
        return \array_values($this->workers);
    }

    public function getPeriodicTasksCount(): int
    {
        return \count($this->periodicTasks);
    }

    /**
     * @return list<PeriodicTask>
     */
    public function getPeriodicTasks(): array
    {
        return \array_values($this->periodicTasks);
    }

    public function getProcessesCount(): int
    {
        return \count($this->processes);
    }

    /**
     * @return list<Process>
     */
    public function getProcesses(): array
    {
        return \array_values($this->processes);
    }

    public function getTotalMemory(): int
    {
        return (int) \array_sum(\array_map(static fn(Process $p) => $p->memory, $this->processes));
    }

    public function getTotalConnections(): int
    {
        return (int) \array_sum(\array_map(static fn(Process $p) => $p->connections, $this->processes));
    }

    public function isDetached(int $pid): bool
    {
        return $this->processes[$pid]->detached ?? false;
    }
}
