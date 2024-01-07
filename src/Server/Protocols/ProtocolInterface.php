<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Protocols;

use Luzrain\PhpRunner\Exception\EncodeTypeError;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;

/**
 * @template TRequest
 * @template TResponse
 */
interface ProtocolInterface
{
    /**
     * Decode package and emit onMessage() callback if answer is not null
     *
     * @return TRequest|null
     */
    public function decode(ConnectionInterface $connection, string $buffer): mixed;

    /**
     * Encode package before sending to client
     *
     * @param TResponse $response
     * @return \Generator<string>
     * @throws EncodeTypeError throws when response data type is not supported
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator;

    /**
     * Emits when an exception occurs while receiving a package
     *
     * @throws \Throwable
     */
    public function onException(ConnectionInterface $connection, \Throwable $e): void;
}
