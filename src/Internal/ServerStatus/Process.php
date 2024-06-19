<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

final class Process
{
    public function __construct(
        public int $pid,
        public string $user,
        public string $name,
        public \DateTimeImmutable $startedAt,
        public int $time = 0,
        public int $memory = 0,
        public bool $detached = false,
        public int $requests = 0,
        public int $rx = 0,
        public int $tx = 0,
        public int $connections = 0,
        public bool $blocked = false,
    ) {
    }
}
