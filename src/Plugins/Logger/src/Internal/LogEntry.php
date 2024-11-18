<?php

declare(strict_types=1);

namespace PHPStreamServer\LoggerPlugin\Internal;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * @internal
 */
final readonly class LogEntry implements MessageInterface
{
    public function __construct(
        public \DateTimeImmutable $time,
        public int $pid,
        public LogLevel $level,
        public string $channel,
        public string $message,
        public array $context = [],
    ) {
    }
}
