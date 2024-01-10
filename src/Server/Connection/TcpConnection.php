<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Connection;

use Luzrain\PhpRunner\Exception\EncodeTypeError;
use Luzrain\PhpRunner\Exception\SendTypeError;
use Luzrain\PhpRunner\Exception\TlsHandshakeException;
use Luzrain\PhpRunner\Server\Protocols\ProtocolInterface;
use Revolt\EventLoop\Driver;

final class TcpConnection implements ConnectionInterface
{
    public const STATUS_ESTABLISHED = 1;
    public const STATUS_CLOSING = 2;
    public const STATUS_CLOSED = 3;

    /**
     * @var resource
     */
    private mixed $socket;
    private \Generator|null $sendBufferLevel1 = null;
    private string $sendBufferLevel2 = '';
    private string $sendBufferCallbackId = '';
    private string $onReadableCallbackId = '';
    private int $status = self::STATUS_ESTABLISHED;

    private string $remoteAddress;
    private string $remoteIp;
    private int $remotePort;
    private string $localAddress;
    private string $localIp;
    private int $localPort;

    // Statistics
    private int $bytesRead = 0;
    private int $bytesWritten = 0;
    private int $requestsCount = 0;

    /**
     * @var \WeakMap<self, array>
     */
    private static \WeakMap $connections;

    /**
     * @param resource $socket
     * @param null|\Closure(self):void $onConnect
     * @param null|\Closure(self, string):void $onMessage
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
            try {
                $this->doTlsHandshake($this->socket);
            } catch (TlsHandshakeException $e) {
                $this->protocol->onException($this, $e);
                throw $e;
            }
        }

        \stream_set_blocking($this->socket, false);
        $this->onReadableCallbackId = $this->eventLoop->onReadable($this->socket, $this->baseRead(...));

        if ($this->onConnect !== null) {
            ($this->onConnect)($this);
        }

        // Statistics
        //self::$statistics['connection_count']++;
        //self::$connections ??= new \WeakMap();
        //self::$connections[$this] = [];
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
        while ('' !== $recvBuffer = \fread($socket, self::READ_BUFFER_SIZE)) {
            // Check connection closed
            if (\feof($socket) || $recvBuffer === false) {
                $this->destroy();
                return;
            }

            $this->bytesRead += \strlen($recvBuffer);

            try {
                if (($package = $this->protocol->decode($this, $recvBuffer)) !== null) {
                    $this->requestsCount++;
                    if ($this->onMessage !== null) {
                        ($this->onMessage)($this, $package);
                    }
                }
            } catch (\Throwable $e) {
                $this->protocol->onException($this, $e);
            }
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
            $this->eventLoop->onWritable($this->socket, $this->baseWrite(...));
        } catch (EncodeTypeError $e) {
            $typeError = new SendTypeError(self::class, $e->acceptType, $e->givenType);
            $this->protocol->onException($this, $typeError);
            throw $typeError;
        }

        return true;
    }


    /**
     * @param resource $socket
     */
    private function baseWrite(string $id, mixed $socket): void
    {
        $len = \fwrite($socket, $this->sendBufferLevel2);
        if ($len === \strlen($this->sendBufferLevel2)) {
            if ($this->sendBufferLevel1?->valid()) {
                $this->sendBufferLevel1->next();
                $this->sendBufferLevel2 = $this->sendBufferLevel1->current() ?? '';
            } else {
                $this->eventLoop->cancel($id);
                $this->sendBufferLevel1 = null;
                $this->sendBufferLevel2 = '';

                if ($this->status === self::STATUS_CLOSING) {
                    $this->destroy();
                }
            }
            $this->bytesWritten += $len;
        } elseif ($len > 0) {
            $this->sendBufferLevel2 = \substr($this->sendBufferLevel2, $len);
            $this->bytesWritten += $len;
        } else {
            //++self::$statistics['send_fail'];
            $this->eventLoop->cancel($id);
            $this->destroy();
            if ($this->onError) {
                ($this->onError)($this, self::SEND_FAIL, 'connection closed');
            }
        }
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

        /** @psalm-suppress InvalidPropertyAssignmentValue */
        \fclose($this->socket);
        $this->eventLoop->cancel($this->onReadableCallbackId);
        $this->eventLoop->cancel($this->sendBufferCallbackId);
        $this->status = self::STATUS_CLOSED;
        $this->sendBufferLevel1 = null;
        $this->sendBufferLevel2 = '';
    }

    public function __destruct()
    {
        //self::$statistics['connection_count']--;
        if ($this->onClose !== null) {
            ($this->onClose)($this);
        }
    }
}
