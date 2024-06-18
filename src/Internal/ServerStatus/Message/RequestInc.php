<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

final readonly class RequestInc
{
    public function __construct(
        public int $pid,
        public int $requests,
    ) {
    }
}
