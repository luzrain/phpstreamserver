<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

final readonly class Detach
{
    public function __construct(
        public int $pid,
    ) {
    }
}
