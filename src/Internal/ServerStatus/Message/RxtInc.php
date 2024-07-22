<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class RxtInc implements Message
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public int $rx,
    ) {
    }
}
