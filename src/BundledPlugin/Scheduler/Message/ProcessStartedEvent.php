<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

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
