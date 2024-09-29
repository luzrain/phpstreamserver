<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\Connection;
use Luzrain\PHPStreamServer\Message;

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
