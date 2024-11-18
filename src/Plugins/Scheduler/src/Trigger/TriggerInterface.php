<?php

declare(strict_types=1);

namespace PHPStreamServer\SchedulerPlugin\Trigger;

interface TriggerInterface extends \Stringable
{
    public function getNextRunDate(\DateTimeImmutable $now): \DateTimeImmutable|null;
}
