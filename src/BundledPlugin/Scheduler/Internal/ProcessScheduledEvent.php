<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Internal;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<void>
 */
final readonly class ProcessScheduledEvent implements Message
{
    public function __construct(
        public int $id,
        public \DateTimeInterface|null $nextRunDate,
    ) {
    }
}
