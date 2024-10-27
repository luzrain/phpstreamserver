<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class LogEntry implements Message
{
    public function __construct(
        public \DateTimeImmutable $time,
        public int $pid,
        public string $level,
        public string $channel,
        public string $message,
        public array $context = [],
    ) {
    }
}
