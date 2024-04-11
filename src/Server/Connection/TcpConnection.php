<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Exception\ConnectionClosedException;
use Luzrain\PHPStreamServer\Exception\ConnectionFailedException;
use Luzrain\PHPStreamServer\Exception\TlsHandshakeException;
use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterTrait;
use Revolt\EventLoop\Driver;

final class TcpConnection implements ConnectionInterface
{
    use EventEmitterTrait;

    public const STATUS_ESTABLISHED = 1;
    public const STATUS_CLOSING = 2;
    public const STATUS_CLOSED = 3;

    /** @var resource */
    private mixed $clientSocket;
    private \Generator|null $sendBufferLevel1 = null;
    private string $sendBufferLevel2 = '';
    private string $onReadableCallbackId = '';
    private string $onWritableCallbackId = '';
    private int $status = self::STATUS_ESTABLISHED;

    private string $remoteAddress;
    private string $remoteIp;
    private int $remotePort;
    private string $localAddress;
    private string $localIp;
    private int $localPort;

    private ConnectionStatistics $connectionStatistics;

    /**
     * @param resource $socket
     */
    public function __construct(
        private readonly mixed $socket,
        private readonly Driver $eventLoop,
        private readonly bool $tls,
        /** @var \Closure(ConnectionInterface, mixed): \Generator $encoder */
        private readonly \Closure $encoder,
    ) {
        $this->connectionStatistics = new ConnectionStatistics();
    }

    public function accept(): void
    {
        if (false === $clientSocket = \stream_socket_accept($this->socket, 0, $remoteAddress)) {
            $this->emit(self::EVENT_ERROR, $this, new ConnectionFailedException());
            return;
        }

        $this->clientSocket = $clientSocket;
        $this->remoteAddress = $remoteAddress;

        // TLS handshake
        if ($this->tls) {
            $tls = new TlsEncryption($this->clientSocket);
            try {
                $tls->encrypt();
                unset($tls);
            } catch (TlsHandshakeException $e) {
                $this->emit(self::EVENT_ERROR, $this, $e);
                return;
            }
        }

        \stream_set_blocking($this->clientSocket, false);
        \stream_set_chunk_size($this->clientSocket, self::READ_CHUNK_SIZE);
        $this->onReadableCallbackId = $this->eventLoop->onReadable($this->clientSocket, $this->baseRead(...));
        $this->emit(self::EVENT_CONNECT, $this);
    }

    /**
     * @param resource $socket
     */
    public function baseRead(string $id, mixed $socket): void
    {
        while (
            '' !== ($recvBuffer = \fread($socket, self::READ_CHUNK_SIZE))
            && $recvBuffer !== false
            && $this->status !== self::STATUS_CLOSING
        ) {
            $this->connectionStatistics->incRx(\strlen($recvBuffer));
            $this->emit(self::EVENT_DATA, $recvBuffer);
        }

        // Check connection closed
        /** @psalm-suppress PossiblyUndefinedVariable */
        if (\feof($socket) || $recvBuffer === false) {
            $this->destroy();
        }
    }

    public function send(mixed $response): bool
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return false;
        }

        $this->sendBufferLevel1 = ($this->encoder)($this, $response);
        $this->sendBufferLevel2 = $this->sendBufferLevel1->current();

        // First try to write directly, if buffer was not flushed completely, then schedule write in event loop
        if (!$this->doWrite()) {
            $this->onWritableCallbackId = $this->eventLoop->onWritable($this->clientSocket, function (string $id) {
                $this->doWrite() && $this->eventLoop->cancel($id);
            });
        }

        return true;
    }

    /**
     * @return bool if buffer write finish or not
     */
    private function doWrite(): bool
    {
        $len = \fwrite($this->clientSocket, $this->sendBufferLevel2);

        if ($len === \strlen($this->sendBufferLevel2)) {
            $this->connectionStatistics->incTx($len);
            $this->sendBufferLevel1?->next();
            if ($this->sendBufferLevel1?->valid()) {
                $this->sendBufferLevel2 = $this->sendBufferLevel1->current() ?? '';
                return false;
            }

            $this->sendBufferLevel1 = null;
            $this->sendBufferLevel2 = '';
            if ($this->status === self::STATUS_CLOSING) {
                $this->eventLoop->queue($this->destroy(...));
            }

            return true;
        }

        if ($len > 0) {
            $this->sendBufferLevel2 = \substr($this->sendBufferLevel2, $len);
            $this->connectionStatistics->incTx($len);

            return false;
        }

        $this->connectionStatistics->incFails();
        $this->destroy();
        $this->emit(self::EVENT_ERROR, $this, new ConnectionClosedException());

        return true;
    }

    public function getRemoteAddress(): string
    {
        return $this->remoteAddress;
    }

    public function getRemoteIp(): string
    {
        if (!isset($this->remoteIp)) {
            $pos = \strrpos($this->getRemoteAddress(), ':');
            $this->remoteIp = $pos !== false ? \trim(\substr($this->getRemoteAddress(), 0, $pos), '[]') : '';
        }

        return $this->remoteIp;
    }

    public function getRemotePort(): int
    {
        return $this->remotePort ??= (int) \substr(\strrchr($this->getRemoteAddress(), ':'), 1);
    }

    public function getLocalAddress(): string
    {
        return $this->localAddress ??= (string) \stream_socket_get_name($this->clientSocket, false);
    }

    public function getLocalIp(): string
    {
        if (!isset($this->localIp)) {
            $pos = \strrpos($this->getLocalAddress(), ':');
            $this->localIp = $pos !== false ? \trim(\substr($this->getLocalAddress(), 0, $pos), '[]') : '';
        }

        return $this->localIp;
    }

    public function getLocalPort(): int
    {
        return $this->localPort ??= (int) \substr(\strrchr($this->getLocalAddress(), ':'), 1);
    }

    public function close(): void
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return;
        }

        $this->status = self::STATUS_CLOSING;
        $this->sendBufferLevel1 === null && $this->sendBufferLevel2 === ''
            ? $this->destroy()
            : $this->eventLoop->disable($this->onReadableCallbackId)
        ;
    }

    private function destroy(): void
    {
        if ($this->status === self::STATUS_CLOSED) {
            return;
        }

        $this->status = self::STATUS_CLOSED;
        $this->eventLoop->cancel($this->onReadableCallbackId);
        $this->eventLoop->cancel($this->onWritableCallbackId);
        $this->sendBufferLevel1 = null;
        $this->sendBufferLevel2 = '';

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        \fclose($this->clientSocket);

        $this->emit(self::EVENT_CLOSE, $this);
        $this->removeAllListeners();
    }

    public function getStatistics(): ConnectionStatistics
    {
        return $this->connectionStatistics;
    }
}
