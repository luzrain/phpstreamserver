<?php

declare(strict_types=1);

namespace PHPStreamServer\SchedulerPlugin\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<void>
 */
final readonly class ProcessStartedEvent implements MessageInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
