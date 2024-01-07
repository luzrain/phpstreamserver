<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Protocols;

use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;

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
