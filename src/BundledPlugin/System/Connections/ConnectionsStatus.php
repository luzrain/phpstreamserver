<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Connections;

use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Event\ConnectionClosedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Event\ConnectionCreatedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Event\RequestCounterIncreaseEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Event\RxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Event\TxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use function Amp\weakClosure;

final class ConnectionsStatus
{
    /**
     * @var array<int, ProcessConnectionsInfo>
     */
    private array $processConnections = [];

    public function __construct()
    {
    }

    public function subscribeToWorkerMessages(MessageHandler $handler): void
    {
        $handler->subscribe(ProcessSpawnedEvent::class, weakClosure(function (ProcessSpawnedEvent $message): void {
            $this->processConnections[$message->pid] = new ProcessConnectionsInfo(
                pid: $message->pid,
            );
        }));

        $handler->subscribe(ProcessDetachedEvent::class, weakClosure(function (ProcessDetachedEvent $message): void {
            unset($this->processConnections[$message->pid]);
        }));

        $handler->subscribe(RxCounterIncreaseEvent::class, weakClosure(function (RxCounterIncreaseEvent $message): void {
            $this->processConnections[$message->pid]->connections[$message->connectionId]->rx += $message->rx;
            $this->processConnections[$message->pid]->rx += $message->rx;
        }));

        $handler->subscribe(TxCounterIncreaseEvent::class, weakClosure(function (TxCounterIncreaseEvent $message): void {
            $this->processConnections[$message->pid]->connections[$message->connectionId]->tx += $message->tx;
            $this->processConnections[$message->pid]->tx += $message->tx;
        }));

        $handler->subscribe(RequestCounterIncreaseEvent::class, weakClosure(function (RequestCounterIncreaseEvent $message): void {
            $this->processConnections[$message->pid]->requests += $message->requests;
        }));

        $handler->subscribe(ConnectionCreatedEvent::class, weakClosure(function (ConnectionCreatedEvent $message): void {
            $this->processConnections[$message->pid]->connections[$message->connectionId] = $message->connection;
        }));

        $handler->subscribe(ConnectionClosedEvent::class, weakClosure(function (ConnectionClosedEvent $message): void {
            unset($this->processConnections[$message->pid]->connections[$message->connectionId]);
        }));
    }

    /**
     * @return list<ProcessConnectionsInfo>
     */
    public function getProcessesConnectionsInfo(): array
    {
        return \array_values($this->processConnections);
    }

    public function getProcessConnectionsInfo(int $pid): ProcessConnectionsInfo
    {
        return $this->processConnections[$pid] ?? new ProcessConnectionsInfo(pid: $pid);
    }

    /**
     * @return list<Connection>
     */
    public function getActiveConnections(): array
    {
        return \array_merge(...\array_map(static fn (ProcessConnectionsInfo $p) => $p->connections, $this->processConnections));
    }
}
