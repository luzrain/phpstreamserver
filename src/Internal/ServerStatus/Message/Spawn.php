<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

final readonly class Spawn
{
    public function __construct(
        public int $pid,
        public string $user,
        public string $name,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
