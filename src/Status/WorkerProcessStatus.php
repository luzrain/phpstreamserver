<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

/**
 * @internal
 */
final readonly class WorkerProcessStatus
{
    public function __construct(
        public int $pid,
        public string $user,
        public int $memory,
        public string $name,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
