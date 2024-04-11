<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Protocols;

use Luzrain\PHPStreamServer\Exception\EncodeTypeError;
use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterInterface;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;

interface ProtocolInterface extends EventEmitterInterface
{
    public const EVENT_MESSAGE = 'message';

    /**
     * Start listening connection and receive message. Once the message is fully received, the "message" event SHOULD be emited
     */
    public function handle(ConnectionInterface $connection): void;

    /**
     * Encode packet before sending to client. MUST be a generator that generate strings.
     *
     * @return \Generator<string>
     * @throws EncodeTypeError throws when response data type is not supported
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator;
}
