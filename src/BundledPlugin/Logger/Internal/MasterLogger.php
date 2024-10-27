<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal;

use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class MasterLogger implements LoggerInterface
{
    use LoggerTrait;

    private string $channel = 'server';

    public function __construct()
    {
    }

    public function withChannel(string $channel): self
    {
        $that = clone $this;
        $that->channel = $channel;

        return $that;
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->logEntry(new LogEntry(
            time: new \DateTimeImmutable('now'),
            pid: \posix_getpid(),
            level: (string) $level,
            channel: $this->channel,
            message: (string) $message,
            context: $context,
        ));
    }

    public function logEntry(LogEntry $logEntry): void
    {
        dump($logEntry->message);
    }
}
