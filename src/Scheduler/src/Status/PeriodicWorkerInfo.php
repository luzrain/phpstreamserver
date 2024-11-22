<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Status;

final class PeriodicWorkerInfo
{
    public function __construct(
        public string $user,
        public string $name,
        public string $schedule,
        public \DateTimeInterface|null $nextRunDate = null,
    ) {
    }
}
