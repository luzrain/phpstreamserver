<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ConnectionStatus\Event;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<void>
 */
final readonly class ConnectionClosedEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $connectionId,
    ) {
    }
}
