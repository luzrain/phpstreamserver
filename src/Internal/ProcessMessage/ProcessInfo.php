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
    ) {
    }

    public function getPid(): int
    {
        return $this->pid;
    }
}
