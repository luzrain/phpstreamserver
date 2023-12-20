<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Connection;

use Luzrain\PhpRunner\Exception\PhpRunnerException;
use Revolt\EventLoop\Driver;

final class TcpConnection implements ConnectionInterface
{
    public const STATUS_ESTABLISHED = 1;
    public const STATUS_CLOSING = 2;
    public const STATUS_CLOSED = 3;

    public const READ_BUFFER_SIZE = 87380;

    /**
     * @var \WeakMap<self, array>
     */
    private static \WeakMap $connections;

    /**
     * Sets the maximum send buffer size for the current connection.
     * OnBufferFull callback will be emitted When send buffer is full.
     */
    public int $maxSendBufferSize = 1048576;

    /**
     * @var resource
     */
    private readonly mixed $socket;
    protected string $recvBuffer = '';
    protected string $sendBuffer = '';

    private string $remoteAddress;
    private string $remoteIp;
    private int $remotePort;
    private string $localAddress;
    private string $localIp;
    private int $localPort;

    private string $onReadableCallbackId;
    private string $onWritableCallbackId = '';
    private int $status = self::STATUS_ESTABLISHED;
    private bool $tlsHandshakeCompleted = false;

    // Statistics
    private int $bytesRead = 0;
    private int $bytesWritten = 0;

    /**
     * @param resource $socket
     * @param null|\Closure(self):void $onConnect
     * @param null|\Closure(self, string):void $onMessage
     * @param null|\Closure(self):void $onClose
     * @param null|\Closure(self):void $onBufferDrain
     * @param null|\Closure(self):void $onBufferFull
     * @param null|\Closure(self, int, string):void $onError
     */
    public function __construct(
        mixed $socket,
        private readonly Driver $eventLoop,
        private readonly bool $tls = false,
        private readonly \Closure|null $onConnect = null,
        private readonly \Closure|null $onMessage = null,
        private readonly \Closure|null $onClose = null,
        private readonly \Closure|null $onBufferDrain = null,
        private readonly \Closure|null $onBufferFull = null,
        private readonly \Closure|null $onError = null,
    ) {
        $this->socket = \stream_socket_accept($socket, 0, $remoteAddress) ?: throw new PhpRunnerException('Socket creation error');
        $this->remoteAddress = $remoteAddress;
        $this->onReadableCallbackId = $this->eventLoop->onReadable($this->socket, $this->baseRead(...));
        \stream_set_blocking($this->socket, false);

        if ($this->onConnect !== null) {
            ($this->onConnect)($this);
        }

        // Statistics
        //self::$statistics['connection_count']++;
        //self::$connections ??= new \WeakMap();
        //self::$connections[$this] = [];
    }

    public function baseRead(string $id, mixed $socket): void
    {
        // TLS handshake
        if ($this->tls === true && $this->tlsHandshakeCompleted === false) {
            if ($this->doTlsHandshake()) {
                if ($this->sendBuffer !== '') {
                    $this->onWritableCallbackId = $this->eventLoop->onWritable($socket, $this->baseWrite(...));
                }
            } else {
                return;
            }
        }

        $buffer = \fread($socket, self::READ_BUFFER_SIZE);

        // Check connection closed
        if ($buffer === '' || $buffer === false) {
            if (\feof($socket) || $buffer === false) {
                $this->destroy();
                return;
            }
        } else {
            $this->recvBuffer .= $buffer;
            $this->bytesRead += \strlen($buffer);
        }

        if ($this->onMessage !== null && $this->recvBuffer !== '') {
            ($this->onMessage)($this, $this->recvBuffer);
        }

        // Clean receive buffer.
        $this->recvBuffer = '';
    }

    public function baseWrite(string $id, mixed $socket): void
    {
        $len = \fwrite($socket, $this->sendBuffer, $this->tls ? 8192 : null);
        if ($len === \strlen($this->sendBuffer)) {
            $this->eventLoop->cancel($id);
            $this->sendBuffer = '';
            $this->bytesWritten += $len;
            if ($this->onBufferDrain) {
                ($this->onBufferDrain)($this);
            }
            if ($this->status === self::STATUS_CLOSING) {
                $this->destroy();
            }
        } elseif ($len > 0) {
            $this->sendBuffer = \substr($this->sendBuffer, $len);
            $this->bytesWritten += $len;
        } else {
            //++self::$statistics['send_fail'];
            $this->destroy();
        }
    }

    private function doTlsHandshake(): bool
    {
        // Connection closed?
        if (\feof($this->socket)) {
            $this->destroy();
            return false;
        }

        // @todo: deprecate ssl?
        $type = STREAM_CRYPTO_METHOD_SSLv2_SERVER | STREAM_CRYPTO_METHOD_SSLv23_SERVER;
        $ret = \stream_socket_enable_crypto($this->socket, true, $type);

        if ($ret === false || 0 === $ret) {
            $this->destroy();
            return false;
        }

        $this->tlsHandshakeCompleted = true;
        return true;
    }


    public function send(string|\Stringable $sendBuffer): bool
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return false;
        }

        $sendBuffer = (string) $sendBuffer;

        if ($this->tls && !$this->tlsHandshakeCompleted) {
            if ($this->sendBuffer !== '' && $this->isBufferFull()) {
                //++self::$statistics['send_fail'];
                return false;
            }
            $this->sendBuffer .= $sendBuffer;
            $this->checkBufferWillFull();
            return false;
        }

        // Attempt to send data directly
        if ($this->sendBuffer === '') {
            if ($this->tls) {
                $this->onWritableCallbackId = $this->eventLoop->onWritable($this->socket, $this->baseWrite(...));
                $this->sendBuffer = $sendBuffer;
                $this->checkBufferWillFull();
                return false;
            }

            $len = \fwrite($this->socket, $sendBuffer);

            // Send successful
            if ($len === \strlen($sendBuffer)) {
                $this->bytesWritten += $len;
                return true;
            }
            // Send only part of the data
            if ($len > 0) {
                $this->sendBuffer = \substr($sendBuffer, $len);
                $this->bytesWritten += $len;
            } else {
                // Connection closed?
                if (\feof($this->socket)) {
                    //++self::$statistics['send_fail'];
                    $this->destroy();
                    if ($this->onError) {
                        ($this->onError)($this, static::SEND_FAIL, 'connection closed');
                    }
                    return false;
                }
                $this->sendBuffer = $sendBuffer;
            }
            $this->onWritableCallbackId = $this->eventLoop->onWritable($this->socket, $this->baseWrite(...));
            // Check if send buffer will be full.
            $this->checkBufferWillFull();
            return true;
        }

        if ($this->isBufferFull()) {
            //++self::$statistics['send_fail'];
            return false;
        }

        $this->sendBuffer .= $sendBuffer;
        // Check if send buffer is full.
        $this->checkBufferWillFull();
        return false;
    }

    protected function isBufferFull(): bool
    {
        // Buffer has been marked as full but still has data to send then the packet is discarded.
        if (\strlen($this->sendBuffer) >= $this->maxSendBufferSize) {
            if ($this->onError) {
                ($this->onError)($this, self::SEND_FAIL, 'Send buffer full and drop package');
            }
            return true;
        }
        return false;
    }

    /**
     * Check whether send buffer will be full.
     *
     * @return void
     */
    protected function checkBufferWillFull(): void
    {
        if ($this->onBufferFull && \strlen($this->sendBuffer) >= $this->maxSendBufferSize) {
            ($this->onBufferFull)($this);
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
        $this->sendBuffer === '' ? $this->destroy() : $this->eventLoop->disable($this->onReadableCallbackId);
    }

    private function destroy(): void
    {
        if ($this->status === self::STATUS_CLOSED) {
            return;
        }

        \fclose($this->socket);
        $this->eventLoop->cancel($this->onReadableCallbackId);
        $this->eventLoop->cancel($this->onWritableCallbackId);
        $this->status = self::STATUS_CLOSED;
        $this->tlsHandshakeCompleted = false;
        $this->sendBuffer = '';
        $this->recvBuffer = '';

        if ($this->onClose !== null) {
            ($this->onClose)($this);
        }
    }

    public function __destruct()
    {
        //self::$statistics['connection_count']--;
    }
}
