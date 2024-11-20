<?php

declare(strict_types=1);

namespace PHPStreamServer\SupervisorPlugin\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class ProcessDetachedEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
    ) {
    }
}
