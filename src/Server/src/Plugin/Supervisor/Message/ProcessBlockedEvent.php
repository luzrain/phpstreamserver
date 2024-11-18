<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Supervisor\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * Process blocked by IO operations
 * @implements MessageInterface<null>
 */
final readonly class ProcessBlockedEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
    ) {
    }
}
