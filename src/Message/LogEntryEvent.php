<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Message;

/**
 * @implements Message<void>
 */
final readonly class LogEntryEvent implements Message
{
    public function __construct(
        public string $level,
        public string $channel,
        public string $message,
        public array $context,
    ) {
    }
}
