<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer\Internal;

use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\Cancellation;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Socket\TlsState;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;

/**
 * @implements \IteratorAggregate<int, string>
 */
final readonly class TrafficCountingSocket implements Socket, \IteratorAggregate
{
    use ReadableStreamIteratorAggregate;

    public function __construct(private Socket $socket, private TrafficStatus $trafficStatisticStore)
    {
    }

    public function close(): void
    {
        $this->socket->close();
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->socket->onClose($onClose);
    }

    public function isReadable(): bool
    {
        return $this->socket->isReadable();
    }

    public function read(?Cancellation $cancellation = null, ?int $limit = null): ?string
    {
        $bytes = $this->socket->read($cancellation, $limit);

        if ($bytes !== null) {
            $this->trafficStatisticStore->incRx($this, \strlen($bytes));
        }

        return $bytes;
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function setupTls(?Cancellation $cancellation = null): void
    {
        $this->socket->setupTls($cancellation);
    }

    public function shutdownTls(?Cancellation $cancellation = null): void
    {
        $this->socket->shutdownTls($cancellation);
    }

    public function isTlsConfigurationAvailable(): bool
    {
        return $this->socket->isTlsConfigurationAvailable();
    }

    public function getTlsState(): TlsState
    {
        return $this->socket->getTlsState();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket->getTlsInfo();
    }

    public function write(string $bytes): void
    {
        $this->socket->write($bytes);
        $this->trafficStatisticStore->incTx($this, \strlen($bytes));
    }

    public function end(): void
    {
        $this->socket->end();
    }

    public function isWritable(): bool
    {
        return $this->socket->isWritable();
    }
}
