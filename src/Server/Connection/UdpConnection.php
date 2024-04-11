<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Exception\ConnectionFailedException;
use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterTrait;

final class UdpConnection implements ConnectionInterface
{
    use EventEmitterTrait;

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
     */
    public function __construct(
        private readonly mixed $socket,
        /** @var \Closure(ConnectionInterface, mixed): \Generator $encoder */
        private readonly \Closure $encoder,
    ) {
        $this->connectionStatistics = new ConnectionStatistics();
    }

    public function accept(): void
    {
        $recvBuffer = \stream_socket_recvfrom($this->socket, self::MAX_UDP_PACKAGE_SIZE, 0, $remoteAddress);
        if ($recvBuffer === false) {
            $this->emit(self::EVENT_ERROR, $this, new ConnectionFailedException());
            return;
        }

        $this->remoteAddress = $remoteAddress;
        $this->connectionStatistics->incRx(\strlen($recvBuffer ?: ''));
        $this->emit(self::EVENT_DATA, $recvBuffer);
    }

    public function send(mixed $response): bool
    {
        $sendBuffer = \implode('', \iterator_to_array(($this->encoder)($this, $response)));
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
