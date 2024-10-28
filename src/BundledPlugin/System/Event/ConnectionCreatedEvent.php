<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Event;

use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\Connection;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

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
