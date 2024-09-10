<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

interface PeriodicProcessInterface extends ProcessInterface
{
    /**
     * Schedule string. Can be one of the following formats:
     *  - Number of seconds
     *  - An ISO8601 datetime format
     *  - An ISO8601 duration format
     *  - A relative date format as supported by \DateInterval
     *  - A cron expression
     */
    public function getSchedule(): string;

    /**
     * Jitter in seconds that adds a random time offset to the schedule
     */
    public function getJitter(): int;
}
