<?php

declare(strict_types=1);

namespace PHPStreamServer\SupervisorPlugin\Status;

final readonly class WorkerInfo
{
    public function __construct(
        public string $user,
        public string $name,
        public int $count,
    ) {
    }
}
