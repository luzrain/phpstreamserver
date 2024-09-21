<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * Process killed
 */
final readonly class Killed implements Message
{
    public function __construct(
        public int $pid,
    ) {
    }
}
