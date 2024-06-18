<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\InterprocessPipe\Interprocess;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connect;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Disconnect;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\RequestInc;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\RxtInc;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Swawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\TxtInc;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcess;
use Revolt\EventLoop\DriverFactory;

/**
 * @todo: force delete connection when process reloaded or detached
 */
final class ServerStatus
{
    public string $version;
    public string $phpVersion;
    public string $eventLoop;
    public string $startFile;
    public \DateTimeImmutable|null $startedAt;
    public bool $isRunning;

    /**
     * @var list<Worker>
     */
    public array $workers = [];

    /**
     * @var array<positive-int, Process>
     */
    public array $processes = [];

    /**
     * @var array<positive-int, Connection>
     */
    public array $connections = [];

    /**
     * @param iterable<WorkerProcess> $workers
     */
    public function __construct(iterable $workers, bool $isRunning)
    {
        $this->version = Server::VERSION;
        $this->phpVersion = PHP_VERSION;
        $this->eventLoop = (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
        $this->startFile = Functions::getStartFile();
        $this->startedAt = $isRunning ? new \DateTimeImmutable('now') : null;
        $this->isRunning = $isRunning;

        foreach ($workers as $worker) {
            $this->workers[] = new Worker($worker->getUser(), $worker->getName(), $worker->getCount());
        }
    }

    public function subscribeToWorkerMessages(Interprocess $interprocess): void
    {
        $interprocess->subscribe(Swawn::class, function (Swawn $message) {
            $this->processes[$message->pid] = new Process(
                pid: $message->pid,
                user: $message->user,
                name: $message->name,
                startedAt: $message->startedAt,
            );
        });

        $interprocess->subscribe(Heartbeat::class, function (Heartbeat $message) {
            $this->processes[$message->pid]->memory = $message->memory;
        });

        $interprocess->subscribe(Detach::class, function (Detach $message) {
            $this->processes[$message->pid]->detached = true;
        });

        $interprocess->subscribe(RxtInc::class, function (RxtInc $message) {
            $this->processes[$message->pid]->rx += $message->rx;
            $this->connections[$this->uniqueConnectionId($message->pid, $message->connectionId)]->rx += $message->rx;
        });

        $interprocess->subscribe(TxtInc::class, function (TxtInc $message) {
            $this->processes[$message->pid]->tx += $message->tx;
            $this->connections[$this->uniqueConnectionId($message->pid, $message->connectionId)]->tx += $message->tx;
        });

        $interprocess->subscribe(RequestInc::class, function (RequestInc $message) {
            $this->processes[$message->pid]->requests += $message->requests;
        });

        $interprocess->subscribe(Connect::class, function (Connect $message) {
            $this->processes[$message->pid]->connections++;
            $this->connections[$this->uniqueConnectionId($message->pid, $message->connectionId)] = new Connection(
                pid: $message->pid,
                connectedAt: $message->connectedAt,
                localIp: $message->localIp,
                localPort: $message->localPort,
                remoteIp: $message->remoteIp,
                remotePort: $message->remotePort,
            );
        });

        $interprocess->subscribe(Disconnect::class, function (Disconnect $message) {
            unset($this->connections[$this->uniqueConnectionId($message->pid, $message->connectionId)]);
            $this->processes[$message->pid]->connections--;
        });
    }

    private function uniqueConnectionId(int $pid, int $connectionId): int
    {
        return (int) ($pid . $connectionId);
    }

    public function deleteProcess(int $pid): void
    {
        unset($this->processes[$pid]);
    }

    public function getWorkersCount(): int
    {
        return \count($this->workers);
    }

    public function getProcessesCount(): int
    {
        return \count($this->processes);
    }

    public function getTotalMemory(): int
    {
        return (int) \array_sum(\array_map(static fn(Process $p) => $p->memory, $this->processes));
    }

    public function isDetached(int $pid): bool
    {
        return $this->processes[$pid]->detached ?? false;
    }
}
