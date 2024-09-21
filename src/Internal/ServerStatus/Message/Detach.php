<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * Process detached
 */
final readonly class Detach implements Message
{
    public function __construct(
        public int $pid,
    ) {
    }
}
