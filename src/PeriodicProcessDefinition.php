<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\PeriodicProcess;

class PeriodicProcessDefinition
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
     * @param null|\Closure(PeriodicProcess):void $onStart
     * @param null|\Closure(PeriodicProcess):void $onStop
     */
    public function __construct(
        public readonly string $name = 'none',
        public readonly string $schedule = '1 minute',
        public readonly int $jitter = 0,
        public string|null $user = null,
        public string|null $group = null,
        public \Closure|null $onStart = null,
        public \Closure|null $onStop = null,
    ) {
    }
}
