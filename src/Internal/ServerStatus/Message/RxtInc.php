<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

final readonly class RxtInc
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public int $rx,
    ) {
    }
}
