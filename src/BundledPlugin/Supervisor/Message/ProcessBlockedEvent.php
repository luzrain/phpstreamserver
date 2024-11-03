<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * Process blocked by IO operations
 * @implements Message<null>
 */
final readonly class ProcessBlockedEvent implements Message
{
    public function __construct(
        public int $pid,
    ) {
    }
}
