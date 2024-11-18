<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\System\Message;

use PHPStreamServer\MessageBus\MessageInterface;
use PHPStreamServer\Plugin\System\Connections\Connection;

/**
 * @implements MessageInterface<null>
 */
final readonly class ConnectionCreatedEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public Connection $connection,
    ) {
    }
}
