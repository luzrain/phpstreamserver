<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Connection;

use Luzrain\PhpRunner\Exception\EncodeTypeError;
use Luzrain\PhpRunner\Exception\SendTypeError;
use Luzrain\PhpRunner\Server\Protocols\ProtocolInterface;

final class UdpConnection implements ConnectionInterface
{
    private const MAX_UDP_PACKAGE_SIZE = 65535;

    private string $remoteAddress;
    private string $remoteIp;
    private int $remotePort;
    private string $localAddress;
    private string $localIp;
    private int $localPort;

    private ConnectionStatistics $connectionStatistics;

    /**
     * @param resource $socket
     * @param null|\Closure(self, string):void $onMessage
     * @param null|\Closure(self, int, string):void $onError
     */
    public function __construct(
        private readonly mixed $socket,
        private readonly ProtocolInterface $protocol,
        private readonly \Closure|null $onMessage = null,
        private readonly \Closure|null $onError = null,
    ) {
        $recvBuffer = \stream_socket_recvfrom($this->socket, self::MAX_UDP_PACKAGE_SIZE, 0, $remoteAddress);
        if ($recvBuffer === false) {
            if ($this->onError) {
                ($this->onError)($this, self::CONNECT_FAIL, 'connection failed');
            }
            return;
        }

        $this->remoteAddress = $remoteAddress;
        $this->connectionStatistics = new ConnectionStatistics();
        $this->connectionStatistics->incRx(\strlen($recvBuffer ?: ''));

        if (($package = $this->protocol->decode($this, $recvBuffer)) !== null) {
            $this->connectionStatistics->incPackages();
            if ($this->onMessage !== null) {
                ($this->onMessage)($this, $package);
            }
        }



        // Increase total counter
        //ConnectionInterface::$statistics['total_request']++;
    }

    public function send(mixed $response): bool
    {
        try {
            $sendBuffer = \implode('', \iterator_to_array($this->protocol->encode($this, $response)));
        } catch (EncodeTypeError $e) {
            $typeError = new SendTypeError(self::class, $e->acceptType, $e->givenType);
            $this->protocol->onException($this, $typeError);
            throw $typeError;
        }

        $this->connectionStatistics->incTx(\strlen($sendBuffer));

        return \stream_socket_sendto($this->socket, $sendBuffer, 0, $this->getRemoteAddress()) !== false;
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

    public function getStatistics(): ConnectionStatistics
    {
        return $this->connectionStatistics;
    }
}
