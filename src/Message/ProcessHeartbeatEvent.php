<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Message;

/**
 * Process sends this message periodically
 * @implements Message<void>
 */
final readonly class ProcessHeartbeatEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $memory,
        public int $time,
    ) {
    }
}
