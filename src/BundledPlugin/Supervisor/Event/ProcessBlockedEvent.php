<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * Process blocked by IO operations
 * @implements Message<void>
 */
final readonly class ProcessBlockedEvent implements Message
{
    public function __construct(
        public int $pid,
    ) {
    }
}
