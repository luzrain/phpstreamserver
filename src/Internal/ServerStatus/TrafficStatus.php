<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

use Amp\Socket\InternetAddress;
use Amp\Socket\Socket;
use Luzrain\PHPStreamServer\Internal\Relay\Relay;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connect;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Disconnect;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\RequestInc;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\RxtInc;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\TxtInc;

/**
 * @readonly
 * @psalm-allow-private-mutation
 */
final class TrafficStatus
{
    /**
     * @var \WeakMap<Socket, Connection>
     */
    private \WeakMap $connectionMap;

    public int $rx = 0;
    public int $tx = 0;
    public int $connections = 0;
    public int $requests = 0;

    public function __construct(private readonly Relay $pipe)
    {
        /**
         * @var \WeakMap<Socket, Connection>
         */
        $this->connectionMap = new \WeakMap();
    }

    /**
     * @return list<Connection>
     */
    public function getConnections(): array
    {
        return \iterator_to_array($this->connectionMap, false);
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
        $this->connectionMap[$socket] = $connection;

        $this->pipe->publish(new Connect(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
            connectedAt: $connection->connectedAt,
            localIp: $connection->localIp,
            localPort: $connection->localPort,
            remoteIp: $connection->remoteIp,
            remotePort: $connection->remotePort,
        ));
    }

    public function removeConnection(Socket $socket): void
    {
        unset($this->connectionMap[$socket]);

        $this->pipe->publish(new Disconnect(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
        ));
    }

    /**
     * @param int<0, max> $val
     */
    public function incRx(Socket $socket, int $val): void
    {
        $this->connectionMap[$socket]->rx += $val;
        $this->rx += $val;

        $this->pipe->publish(new RxtInc(
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
        $this->connectionMap[$socket]->tx += $val;
        $this->tx += $val;

        $this->pipe->publish(new TxtInc(
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

        $this->pipe->publish(new RequestInc(
            pid: \posix_getpid(),
            requests: $val,
        ));
    }
}
