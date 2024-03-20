<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Protocols;

use Luzrain\PHPStreamServer\Exception\EncodeTypeError;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;

/**
 * @template TRequest
 * @template TResponse
 */
interface ProtocolInterface
{
    /**
     * Decode packet.
     * This method may be called several times while the packet is receiving sequences of the packet on $buffer.
     * Protocol should store parts of the package somewhere in the buffer and MUST return null if it knows that packet is incomplete.
     * When the last part of the packet is received, the method MUST return the complete message. It can be any object based on the data received.
     *
     * @return TRequest|null
     */
    public function decode(ConnectionInterface $connection, string $buffer): mixed;

    /**
     * Encode packet before sending to client.
     * MUST be a generator that generate strings.
     *
     * @param TResponse $response
     * @return \Generator<string>
     * @throws EncodeTypeError throws when response data type is not supported
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator;

    /**
     * Emits when an exception occurs while receiving a packet.
     *
     * @throws \Throwable
     */
    public function onException(ConnectionInterface $connection, \Throwable $e): void;
}
