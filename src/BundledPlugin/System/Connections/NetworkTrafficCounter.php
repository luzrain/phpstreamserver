<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Connections;

use Amp\Socket\InternetAddress;
use Amp\Socket\Socket;
use Luzrain\PHPStreamServer\BundledPlugin\System\Message\ConnectionClosedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Message\ConnectionCreatedEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Message\RequestCounterIncreaseEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Message\RxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\BundledPlugin\System\Message\TxCounterIncreaseEvent;
use Luzrain\PHPStreamServer\MessageBus\MessageBus;

/**
 * @TODO: throttle dispatching
 */
final readonly class NetworkTrafficCounter
{
    public function __construct(private MessageBus $bus)
    {
    }

    public function addConnection(Socket $socket): void
    {
        $localAddress = $socket->getLocalAddress();
        $remoteAddress = $socket->getRemoteAddress();
        \assert($localAddress instanceof InternetAddress);
        \assert($remoteAddress instanceof InternetAddress);

        $this->bus->dispatch(new ConnectionCreatedEvent(
            pid: \posix_getpid(),
            connectionId: \spl_object_id($socket),
            connection: new Connection(
                pid: \posix_getpid(),
                connectedAt: new \DateTimeImmutable('now'),
                localIp: $localAddress->getAddress(),
                localPort: (string) $localAddress->getPort(),
                remoteIp: $remoteAddress->getAddress(),
                remotePort: (string) $remoteAddress->getPort(),
            ),
        ));
    }

    public function removeConnection(Socket $socket): void
    {
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
        $this->bus->dispatch(new RequestCounterIncreaseEvent(
            pid: \posix_getpid(),
            requests: $val,
        ));
    }
}
