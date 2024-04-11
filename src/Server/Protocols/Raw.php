<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Protocols;

use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterTrait;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;

final class Raw implements ProtocolInterface
{
    use EventEmitterTrait;

    public function handle(ConnectionInterface $connection): void
    {
        $connection->on($connection::EVENT_DATA, function (string $buffer) use (&$connection) {
            $this->emit(self::EVENT_MESSAGE, $connection, $buffer);
        });
    }

    public function encode(ConnectionInterface $connection, mixed $response): \Generator
    {
        yield $response;
    }
}
