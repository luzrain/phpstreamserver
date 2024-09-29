<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Message;

/**
 * @implements Message<void>
 */
final readonly class ProcessExitedEvent implements Message
{
    public function __construct(
        public int $pid,
    ) {
    }
}
