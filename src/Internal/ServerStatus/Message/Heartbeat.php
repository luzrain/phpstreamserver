<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

final readonly class Heartbeat
{
    public function __construct(
        public int $pid,
        public int $memory,
    ) {
    }
}
