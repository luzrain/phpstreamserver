<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Exception\TlsHandshakeException;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PHPStreamServer\Server\Connection\ActiveConnection;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;
use Luzrain\PHPStreamServer\Server\Connection\TcpConnection;
use Luzrain\PHPStreamServer\Server\Connection\UdpConnection;
use Luzrain\PHPStreamServer\Server\Protocols\ProtocolInterface;
use Luzrain\PHPStreamServer\Server\Protocols\Raw;
use Revolt\EventLoop\Driver;

final class Listener
{
    private string $transport;
    private string $host;
    private int $port;
    private array $socketContextData = [];
    private Driver $eventLoop;
    private string $onReadableCallbackIdentifier;
    /** @var array<ReloadStrategyInterface> */
    private array $reloadStrategies = [];
    private \Closure $reloadCallback;

    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     */
    private const DEFAULT_BACKLOG = 102400;

    /**
     * @param null|\Closure(ConnectionInterface): void $onConnect
     * @param null|\Closure(ConnectionInterface, mixed): void $onMessage
     * @param null|\Closure(ConnectionInterface):void $onClose
     * @param null|\Closure(ConnectionInterface, int, string):void $onError
     */
    public function __construct(
        string $listen,
        private readonly ProtocolInterface $protocol = new Raw(),
        private readonly bool $tls = false,
        string|null $tlsCertificate = null,
        string|null $tlsCertificateKey = null,
        private readonly \Closure|null $onConnect = null,
        private readonly \Closure|null $onMessage = null,
        private readonly \Closure|null $onClose = null,
        private readonly \Closure|null $onError = null,
    ) {
        [$this->transport, $this->host, $this->port] = $this->parseListenAddress($listen);

        $this->socketContextData['socket']['backlog'] = self::DEFAULT_BACKLOG;
        $this->socketContextData['socket']['so_reuseport'] = 1;
        $this->socketContextData['ssl']['verify_peer'] = false;
        if ($tls && $tlsCertificate !== null) {
            $this->socketContextData['ssl']['local_cert'] = $tlsCertificate;
        }
        if ($tls && $tlsCertificateKey !== null) {
            $this->socketContextData['ssl']['local_pk'] = $tlsCertificateKey;
        }
    }

    private function parseListenAddress(string $listen): array
    {
        $parts = \parse_url($listen);
        $transport = \strtolower($parts['scheme'] ?? 'tcp');
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? 0;

        if (!\in_array($transport, ['tcp', 'udp'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid transport. Should be either "tcp" or "udp", "%s" given.', $transport));
        }
        if (empty($host)) {
            throw new \InvalidArgumentException('Invalid address. Should not be empty.');
        }
        if ($port <= 0) {
            throw new \InvalidArgumentException('Invalid port. Should be greater than 0.');
        }

        return [$transport, $host, $port];
    }

    /**
     * @param array<ReloadStrategyInterface> $reloadStrategies
     */
    public function start(Driver $eventLoop, array &$reloadStrategies, \Closure $reloadCallback): void
    {
        $errno = 0;
        $errmsg = '';
        $flags = $this->transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $listenAddress = "{$this->transport}://{$this->host}:{$this->port}";
        $socketContext = \stream_context_create($this->socketContextData);
        if (false === $mainSocket = \stream_socket_server($listenAddress, $errno, $errmsg, $flags, $socketContext)) {
            return;
        }
        \stream_set_blocking($mainSocket, false);
        $this->eventLoop = $eventLoop;
        $this->onReadableCallbackIdentifier = $this->transport === 'tcp'
            ? $this->eventLoop->onReadable($mainSocket, $this->acceptTcpConnection(...))
            : $this->eventLoop->onReadable($mainSocket, $this->acceptUdpConnection(...));
        $this->reloadStrategies = &$reloadStrategies;
        $this->reloadCallback = $reloadCallback;
    }

    public function stop(): void
    {
        if (isset($this->eventLoop)) {
            $this->eventLoop->cancel($this->onReadableCallbackIdentifier);
        }
    }

    /**
     * @param string $id callback id
     * @param resource $socket
     */
    private function acceptUdpConnection(string $id, mixed $socket): void
    {
        $connection = new UdpConnection(
            socket: $socket,
            encoder: $this->protocol->encode(...),
        );

        $this->protocol->handle($connection);

        if ($this->onMessage !== null) {
            $this->protocol->on($this->protocol::EVENT_MESSAGE, $this->onMessage);
        }

        if ($this->onError !== null) {
            $connection->on($connection::EVENT_ERROR, $this->onError);
        }

        $this->protocol->on($this->protocol::EVENT_MESSAGE, $this->processMessage(...));

        $connection->accept();
    }

    /**
     * @param string $id callback id
     * @param resource $socket
     * @throws TlsHandshakeException
     */
    private function acceptTcpConnection(string $id, mixed $socket): void
    {
        $connection = new TcpConnection(
            socket: $socket,
            eventLoop: $this->eventLoop,
            tls: $this->tls,
            encoder: $this->protocol->encode(...),
        );

        $this->protocol->handle($connection);

        if ($this->onConnect !== null) {
            $connection->on($connection::EVENT_CONNECT, $this->onConnect);
        }
        if ($this->onMessage !== null) {
            $this->protocol->on($this->protocol::EVENT_MESSAGE, $this->onMessage);
        }
        if ($this->onClose !== null) {
            $connection->on($connection::EVENT_CLOSE, $this->onClose);
        }
        if ($this->onError !== null) {
            $connection->on($connection::EVENT_ERROR, $this->onError);
        }

        $this->protocol->on($this->protocol::EVENT_MESSAGE, $this->processMessage(...));

        $connection->on($connection::EVENT_CONNECT, static function (ConnectionInterface $connection): void {
            ActiveConnection::addConnection($connection);
        });

        $connection->accept();
    }

    private function processMessage(ConnectionInterface $connection, mixed $packet): void
    {
        $connection->getStatistics()->incPackages();

        foreach ($this->reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy->shouldReload($reloadStrategy::EVENT_CODE_REQUEST, $packet)) {
                $this->eventLoop->defer(function (): void {
                    ($this->reloadCallback)();
                });
            }
        }
    }

    public function getListenAddress(): string
    {
        return \sprintf('%s://%s:%d', $this->transport, $this->host, $this->port);
    }
}
