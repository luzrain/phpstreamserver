<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<void>
 */
final readonly class RequestCounterIncreaseEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $requests,
    ) {
    }
}
