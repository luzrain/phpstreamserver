<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

/**
 * @internal
 */
final readonly class WorkerStatus
{
    public function __construct(
        public string $user,
        public string $name,
        public int $count,
    ) {
    }
}
