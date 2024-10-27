<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ConnectionStatus;

use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessDetachedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\Internal\ConnectionStatus\Event\ConnectionClosedEvent;
use Luzrain\PHPStreamServer\Internal\ConnectionStatus\Event\ConnectionCreatedEvent;
use Luzrain\PHPStreamServer\Internal\ConnectionStatus\Event\RequestCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Internal\ConnectionStatus\Event\RxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Internal\ConnectionStatus\Event\TxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use function Amp\weakClosure;

/**
 * @readonly
 * @psalm-allow-private-mutation
 */
final class ConnectionsStatus
{
    /**
     * @var array<int, ProcessConnectionsInfo>
     */
    private array $processes = [];

    public function __construct()
    {
    }

    public function subscribeToWorkerMessages(MessageHandler $handler): void
    {
        $handler->subscribe(ProcessSpawnedEvent::class, weakClosure(function (ProcessSpawnedEvent $message): void {
            $this->processes[$message->pid] = new ProcessConnectionsInfo(
                pid: $message->pid,
            );
        }));

        $handler->subscribe(ProcessDetachedEvent::class, weakClosure(function (ProcessDetachedEvent $message): void {
            unset($this->processes[$message->pid]);
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

    public function getProcessConnections(): array
    {
        return \array_values($this->processes);
    }

    public function getTotalConnections(): int
    {
        return (int) \array_sum(\array_map(static fn(ProcessConnectionsInfo $p): array => $p->connections, $this->processes));
    }
}
