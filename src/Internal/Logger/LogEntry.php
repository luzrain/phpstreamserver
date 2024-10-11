<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

final readonly class LogEntry
{
    public function __construct(
        public \DateTimeImmutable $time,
        public string $level,
        public string $channel,
        public string $message,
        public array $context = [],
    ) {
    }
}
