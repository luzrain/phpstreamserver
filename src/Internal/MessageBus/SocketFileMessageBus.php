<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\Future;
use Amp\Socket\Socket;
use function Amp\async;
use function Amp\Socket\connect;

final class SocketFileMessageBus implements MessageBus
{
    public const CHUNK_SIZE = 1048576;

    private Socket|null $socket = null;

    public function __construct(private readonly string $socketFile)
    {
    }

    public function dispatch(Message $message): Future
    {
        if ($this->socket === null && \file_exists($this->socketFile)) {
            $this->socket = connect("unix://{$this->socketFile}");
        }

        if ($this->socket === null) {
            return async(static fn () => null);
        }

        $payload = \serialize($message);
        $socket = &$this->socket;

        return async(static function () use ($payload, &$socket): mixed {
            $socket->write($payload);
            $buffer = $socket->read(null, self::CHUNK_SIZE);

            return \unserialize($buffer);
        });
    }
}
