<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server;

use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Connection\TcpConnection;
use Luzrain\PhpRunner\Server\Connection\UdpConnection;
use Revolt\EventLoop\Driver;

final class Server
{
    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     *
     * @var int
     */
    private const DEFAULT_BACKLOG = 102400;

    private bool $reusePort = false;

    /**
     * @param null|\Closure(ConnectionInterface): void $onConnect
     * @param null|\Closure(ConnectionInterface, string): void $onMessage
     * @param null|\Closure(ConnectionInterface):void $onClose
     * @param null|\Closure(ConnectionInterface):void $onBufferDrain
     * @param null|\Closure(ConnectionInterface):void $onBufferFull
     * @param null|\Closure(ConnectionInterface, int, string):void $onError
     */
    public function __construct(
        private readonly Driver $eventLoop,
        private readonly \Closure|null $onConnect = null,
        private readonly \Closure|null $onMessage = null,
        private readonly \Closure|null $onClose = null,
        private readonly \Closure|null $onBufferDrain = null,
        private readonly \Closure|null $onBufferFull = null,
        private readonly \Closure|null $onError = null,
    ) {
    }

    public function listen(string $listen): void
    {
        $transport = \parse_url($listen, PHP_URL_SCHEME);

        $socketContextData = [];
        $socketContextData['socket']['backlog'] ??= self::DEFAULT_BACKLOG;
        if ($this->reusePort) {
            $socketContextData['socket']['so_reuseport'] ??= 1;
        }

        // create a server socket
        $errno = 0;
        $errmsg = '';
        $flags = $transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $mainSocket = \stream_socket_server($listen, $errno, $errmsg, $flags, \stream_context_create($socketContextData));
        \stream_set_blocking($mainSocket, false);

        $transport === 'tcp'
            ? $this->eventLoop->onReadable($mainSocket, $this->acceptTcpConnection(...))
            : $this->eventLoop->onReadable($mainSocket, $this->acceptUdpConnection(...))
        ;
    }

    /**
     * @param resource $socket
     */
    private function acceptUdpConnection(string $id, mixed $socket): void
    {
        new UdpConnection(
            socket: $socket,
            onMessage: $this->onMessage
        );
    }

    /**
     * @param resource $socket
     */
    private function acceptTcpConnection(string $id, mixed $socket): void
    {
        new TcpConnection(
            socket: $socket,
            eventLoop: $this->eventLoop,
            onConnect: $this->onConnect,
            onMessage: $this->onMessage,
            onClose: $this->onClose,
            onBufferDrain: $this->onBufferDrain,
            onBufferFull: $this->onBufferFull,
            onError: $this->onError,
        );
    }
}
