<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

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
