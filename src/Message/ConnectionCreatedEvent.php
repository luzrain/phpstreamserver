<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\Connection;

/**
 * @implements Message<void>
 */
final readonly class ConnectionCreatedEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public Connection $connection,
    ) {
    }
}
