<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Supervisor\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * Process sends this message periodically
 * @implements MessageInterface<null>
 */
final readonly class ProcessHeartbeatEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
        public int $memory,
        public int $time,
    ) {
    }
}
