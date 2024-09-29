<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus;

use Amp\Socket\InternetAddress;
use Amp\Socket\Socket;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Message\ConnectionClosedEvent;
use Luzrain\PHPStreamServer\Message\ConnectionCreatedEvent;
use Luzrain\PHPStreamServer\Message\RequestCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Message\RxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\Message\TxCounterIncreaseEvent;

final class NetworkTrafficCounter
{
    /** @var \WeakMap<Socket, Connection> */
    private \WeakMap $activeConnections;
    private int $rx = 0;
    private int $tx = 0;
    private int $connections = 0;
    private int $requests = 0;

    public function __construct(private readonly MessageBus $bus)
    {
        $this->activeConnections = new \WeakMap();
    }

    public function addConnection(Socket $socket): void
    {
        $localAddress = $socket->getLocalAddress();
        $remoteAddress = $socket->getRemoteAddress();
        \assert($localAddress instanceof InternetAddress);
        \assert($remoteAddress instanceof InternetAddress);

        $connection = new Connection(
            pid: \posix_getpid(),
            connectedAt: new \DateTimeImmutable('now'),
            localIp: $localAddress->getAddress(),
            localPort: (string) $localAddress->getPort(),
            remoteIp: $remoteAddress->getAddress(),
            remotePort: (string) $remoteAddress->getPort(),
        );

        $this->connections++;
        $this->activeConnections[$socket] = $connection;

        $this->bus->dispatch(new ConnectionCreatedEvent(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
            connection: $connection,
        ));
    }

    public function removeConnection(Socket $socket): void
    {
        unset($this->activeConnections[$socket]);

        $this->bus->dispatch(new ConnectionClosedEvent(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
        ));
    }

    /**
     * @param int<0, max> $val
     */
    public function incRx(Socket $socket, int $val): void
    {
        /**
         * @TODO! dispatch only once by the end of request!
         */

        $this->activeConnections[$socket]->rx += $val;
        $this->rx += $val;

        $this->bus->dispatch(new RxCounterIncreaseEvent(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
            rx: $val,
        ));
    }

    /**
     * @param int<0, max> $val
     */
    public function incTx(Socket $socket, int $val): void
    {
        $this->activeConnections[$socket]->tx += $val;
        $this->tx += $val;

        $this->bus->dispatch(new TxCounterIncreaseEvent(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
            tx: $val,
        ));
    }

    /**
     * @param int<0, max> $val
     */
    public function incRequests(int $val = 1): void
    {
        $this->requests += $val;

        $this->bus->dispatch(new RequestCounterIncreaseEvent(
            pid: \posix_getpid(),
            requests: $val,
        ));
    }

    /**
     * @return list<Connection>
     */
    public function getConnections(): array
    {
        return \iterator_to_array($this->activeConnections, false);
    }

    public function getTotalRx(): int
    {
        return $this->rx;
    }

    public function getTotalTx(): int
    {
        return $this->tx;
    }

    public function getTotalConnection(): int
    {
        return $this->connections;
    }

    public function getTotalRequests(): int
    {
        return $this->requests;
    }
}
