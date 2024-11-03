<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * @implements Message<null>
 */
final readonly class TxCounterIncreaseEvent implements Message
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public int $tx,
    ) {
    }
}
