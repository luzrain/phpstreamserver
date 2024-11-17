<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class RequestCounterIncreaseEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
        public int $requests,
    ) {
    }
}
