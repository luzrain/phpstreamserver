<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<void>
 */
final readonly class ProcessScheduledEvent implements MessageInterface
{
    public function __construct(
        public int $id,
        public \DateTimeInterface|null $nextRunDate,
    ) {
    }
}
