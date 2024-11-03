<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * @implements Message<null>
 */
final readonly class ProcessDetachedEvent implements Message
{
    public function __construct(
        public int $pid,
    ) {
    }
}
