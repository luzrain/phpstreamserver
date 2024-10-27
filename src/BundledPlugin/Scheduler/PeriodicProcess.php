<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler;

use Luzrain\PHPStreamServer\Process;

class PeriodicProcess extends Process
{
    /**
     * $schedule can be one of the following formats:
     *  - Number of seconds
     *  - An ISO8601 datetime format
     *  - An ISO8601 duration format
     *  - A relative date format as supported by \DateInterval
     *  - A cron expression
     *
     * @param string $schedule Schedule in one of the formats described above
     * @param int $jitter Jitter in seconds that adds a random time offset to the schedule
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     */
    public function __construct(
        string $name = 'none',
        public readonly string $schedule = '1 minute',
        public readonly int $jitter = 0,
        string|null $user = null,
        string|null $group = null,
        \Closure|null $onStart = null,
        \Closure|null $onStop = null,
    ) {
        parent::__construct(name: $name, user: $user, group: $group, onStart: $onStart, onStop: $onStop);
    }

    protected function start(): void
    {
        $this->stop();
    }
}
