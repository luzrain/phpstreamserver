<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Connection;

final class UdpConnection implements ConnectionInterface
{
    private const MAX_UDP_PACKAGE_SIZE = 65535;

    private string $remoteAddress;
    private string $remoteIp;
    private int $remotePort;
    private string $localAddress;
    private string $localIp;
    private int $localPort;

    /**
     * @param resource $socket
     * @param null|\Closure(self, string):void $onMessage
     */
    public function __construct(
        private readonly mixed $socket,
        private readonly \Closure|null $onMessage = null,
    ) {
        $recvBuffer = \stream_socket_recvfrom($this->socket, self::MAX_UDP_PACKAGE_SIZE, 0, $remoteAddress);
        $this->remoteAddress = $remoteAddress;

        if ($recvBuffer !== false && $this->onMessage !== null) {
            ($this->onMessage)($this, $recvBuffer);
        }

        // Increase total counter
        //ConnectionInterface::$statistics['total_request']++;
    }

    public function send(mixed $response): bool
    {
        return \stream_socket_sendto($this->socket, (string) $response, 0, $this->getRemoteAddress()) !== false;
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
        // no action
    }
}
