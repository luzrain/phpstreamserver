<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Protocols;

use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;

/**
 * @implements ProtocolInterface<string, string>
 */
final class Raw implements ProtocolInterface
{
    public function decode(ConnectionInterface $connection, string $buffer): string
    {
        return $buffer;
    }

    public function encode(ConnectionInterface $connection, mixed $response): \Generator
    {
        yield $response;
    }

    public function onException(ConnectionInterface $connection, \Throwable $e): void
    {
        throw $e;
    }
}
