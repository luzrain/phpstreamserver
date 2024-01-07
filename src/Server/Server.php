<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server;

use Luzrain\PhpRunner\Exception\TlsHandshakeException;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Connection\TcpConnection;
use Luzrain\PhpRunner\Server\Connection\UdpConnection;
use Luzrain\PhpRunner\Server\Protocols\ProtocolInterface;
use Luzrain\PhpRunner\Server\Protocols\Raw;
use Revolt\EventLoop\Driver;

final class Server
{
    private string $transport;
    private string $host;
    private int $port;
    private array $socketContextData = [];
    private Driver $eventLoop;

    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     */
    private const DEFAULT_BACKLOG = 102400;

    /**
     * @param null|\Closure(ConnectionInterface): void $onConnect
     * @param null|\Closure(ConnectionInterface, string): void $onMessage
     * @param null|\Closure(ConnectionInterface):void $onClose
     * @param null|\Closure(ConnectionInterface, int, string):void $onError
     */
    public function __construct(
        string $listen,
        private ProtocolInterface $protocol = new Raw(),
        private readonly bool $tls = false,
        string $tlsCertificate = '',
        string $tlsCertificateKey = '',
        private readonly \Closure|null $onConnect = null,
        private readonly \Closure|null $onMessage = null,
        private readonly \Closure|null $onClose = null,
        private readonly \Closure|null $onError = null,
    ) {
        [$this->transport, $this->host, $this->port] = $this->parseListenAddress($listen);

        $this->socketContextData['socket']['backlog'] = self::DEFAULT_BACKLOG;
        $this->socketContextData['socket']['so_reuseport'] = 1;
        if ($tls && $tlsCertificate !== '') {
            $this->socketContextData['ssl']['local_cert'] = $tlsCertificate;
        }
        if ($tls && $tlsCertificateKey !== '') {
            $this->socketContextData['ssl']['local_pk'] = $tlsCertificateKey;
        }
    }

    private function parseListenAddress(string $listen): array
    {
        $parts = \parse_url($listen);
        $transport = \strtolower($parts['scheme'] ?? 'tcp');
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? 0;

        if (!\in_array($transport, ['tcp', 'udp'])) {
            throw new \InvalidArgumentException(\sprintf('Invalid transport. Should be either "tcp" or "udp", "%s" given.', $transport));
        }
        if (empty($host)) {
            throw new \InvalidArgumentException('Invalid address. Should not be empty');
        }
        if ($port <= 0) {
            throw new \InvalidArgumentException('Invalid port. Should be greater than 0.');
        }

        return [$transport, $host, $port];
    }

    public function start(Driver $eventLoop): void
    {
        $errno = 0;
        $errmsg = '';
        $flags = $this->transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $listenAddress = "{$this->transport}://{$this->host}:{$this->port}";
        $socketContext = \stream_context_create($this->socketContextData);
        $mainSocket = \stream_socket_server($listenAddress, $errno, $errmsg, $flags, $socketContext);
        \stream_set_blocking($mainSocket, false);
        $this->eventLoop = $eventLoop;
        $this->transport === 'tcp'
            ? $this->eventLoop->onReadable($mainSocket, $this->acceptTcpConnection(...))
            : $this->eventLoop->onReadable($mainSocket, $this->acceptUdpConnection(...))
        ;
    }

    /**
     * @param string $id callback id
     * @param resource $socket
     */
    private function acceptUdpConnection(string $id, mixed $socket): void
    {
        new UdpConnection(
            socket: $socket,
            protocol: clone $this->protocol,
            onMessage: $this->onMessage,
            onError: $this->onError,
        );
    }

    /**
     * @param string $id callback id
     * @param resource $socket
     * @throws TlsHandshakeException
     */
    private function acceptTcpConnection(string $id, mixed $socket): void
    {
        new TcpConnection(
            socket: $socket,
            eventLoop: $this->eventLoop,
            protocol: clone $this->protocol,
            tls: $this->tls,
            onConnect: $this->onConnect,
            onMessage: $this->onMessage,
            onClose: $this->onClose,
            onError: $this->onError,
        );
    }
}
