<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Scheduler\Message;

use PHPStreamServer\Core\MessageBus\MessageInterface;
use PHPStreamServer\Plugin\Scheduler\Status\SchedulerStatus;

/**
 * @implements MessageInterface<SchedulerStatus>
 */
final class GetSchedulerStatusCommand implements MessageInterface
{
}
