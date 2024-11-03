<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class TxCounterIncreaseEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public int $tx,
    ) {
    }
}
