<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ProcessMessage;

/**
 * @internal
 */
final readonly class ProcessInfo implements Message
{
    public function __construct(
        public int $pid,
        public string $name,
        public string $user,
        public \DateTimeImmutable $startedAt,
        public bool $isDetached,
        public int $memory = 0,
        public string $listen = '',
        public int $rx = 0,
        public int $tx = 0,
        public int $connections = 0,
        public int $requests = 0,
    ) {
    }

    public function getPid(): int
    {
        return $this->pid;
    }
}
