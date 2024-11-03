<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * @implements Message<null>
 */
final readonly class ConnectionClosedEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $connectionId,
    ) {
    }
}
