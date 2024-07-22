<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class Heartbeat implements Message
{
    public function __construct(
        public int $pid,
        public int $memory,
        public int $time,
    ) {
    }
}
