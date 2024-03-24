<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Exception\EncodeTypeError;
use Luzrain\PHPStreamServer\Exception\SendTypeError;
use Luzrain\PHPStreamServer\Exception\TlsHandshakeException;
use Luzrain\PHPStreamServer\Server\Protocols\ProtocolInterface;
use Revolt\EventLoop\Driver;

final class TcpConnection implements ConnectionInterface
{
    public const STATUS_ESTABLISHED = 1;
    public const STATUS_CLOSING = 2;
    public const STATUS_CLOSED = 3;

    /** @var resource */
    private mixed $socket;
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
     * @param null|\Closure(self):void $onConnect
     * @param null|\Closure(self, mixed):void $onMessage
     * @param null|\Closure(self):void $onClose
     * @param null|\Closure(self, int, string):void $onError
     */
    public function __construct(
        mixed $socket,
        private readonly Driver $eventLoop,
        private readonly ProtocolInterface $protocol,
        private readonly bool $tls = false,
        private readonly \Closure|null $onConnect = null,
        private readonly \Closure|null $onMessage = null,
        private readonly \Closure|null $onClose = null,
        private readonly \Closure|null $onError = null,
    ) {
        $clientSocket = \stream_socket_accept($socket, 0, $remoteAddress);
        if ($clientSocket === false) {
            if ($this->onError) {
                ($this->onError)($this, self::CONNECT_FAIL, 'connection failed');
            }
            return;
        }
        $this->socket = $clientSocket;
        $this->remoteAddress = $remoteAddress;

        // TLS handshake
        if ($this->tls) {
            \stream_set_blocking($this->socket, true);
            try {
                $this->doTlsHandshake($this->socket);
            } catch (TlsHandshakeException $e) {
                $this->protocol->onException($this, $e);
                throw $e;
            }
        }

        \stream_set_blocking($this->socket, false);
        $this->onReadableCallbackId = $this->eventLoop->onReadable($this->socket, $this->baseRead(...));
        $this->connectionStatistics = new ConnectionStatistics();
        ActiveConnection::addConnection($this);

        if ($this->onConnect !== null) {
            ($this->onConnect)($this);
        }
    }

    /**
     * @param resource $socket
     * @throws TlsHandshakeException
     */
    private function doTlsHandshake(mixed $socket): void
    {
        $error = '';
        \set_error_handler(static function (int $type, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        $tlsHandshakeCompleted = (bool) \stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
        \restore_error_handler();
        if (!$tlsHandshakeCompleted) {
            throw new TlsHandshakeException($error);
        }
    }

    /**
     * @param resource $socket
     */
    public function baseRead(string $id, mixed $socket): void
    {
        while (!empty($recvBuffer = \fread($socket, self::READ_BUFFER_SIZE))) {
            $this->connectionStatistics->incRx(\strlen($recvBuffer));
            try {
                if (($packet = $this->protocol->decode($this, $recvBuffer)) !== null) {
                    $this->connectionStatistics->incPackages();
                    if ($this->onMessage !== null) {
                        ($this->onMessage)($this, $packet);
                    }
                }
            } catch (\Throwable $e) {
                $this->protocol->onException($this, $e);
            }
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

        try {
            $this->sendBufferLevel1 = $this->protocol->encode($this, $response);
            $this->sendBufferLevel2 = $this->sendBufferLevel1->current();
            // First try to write directly, if buffer was not flushed completely, then schedule write in event loop
            if (!$this->doWrite()) {
                $this->onWritableCallbackId = $this->eventLoop->onWritable($this->socket, function (string $id) {
                    $this->doWrite() && $this->eventLoop->cancel($id);
                });
            }
        } catch (EncodeTypeError $e) {
            $typeError = new SendTypeError(self::class, $e->acceptType, $e->givenType);
            $this->protocol->onException($this, $typeError);
            throw $typeError;
        }

        return true;
    }

    /**
     * @return bool if buffer write finish or not
     */
    private function doWrite(): bool
    {
        $len = \fwrite($this->socket, $this->sendBufferLevel2);

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
        if ($this->onError) {
            ($this->onError)($this, self::SEND_FAIL, 'connection closed');
        }

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
        return $this->localAddress ??= (string) \stream_socket_get_name($this->socket, false);
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
        \fclose($this->socket);

        if ($this->onClose !== null) {
            ($this->onClose)($this);
        }
    }

    public function getStatistics(): ConnectionStatistics
    {
        return $this->connectionStatistics;
    }
}
