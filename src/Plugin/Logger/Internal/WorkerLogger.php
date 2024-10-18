<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Logger\Internal;

use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MessageBus\CompositeMessage;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Psr\Log\LoggerTrait;
use Revolt\EventLoop;

/**
 * @internal
 */
final class WorkerLogger implements LoggerInterface
{
    private const MAX_FLUSH_SIZE = 1200;
    private const MAX_FLUSH_TIME = 0.01;

    use LoggerTrait;

    private array $log = [];
    private string $channel = 'worker';
    private string $callbackId = '';

    public function __construct(private MessageBus $messageBus)
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
        $event = new LogEntry(
            time: new \DateTimeImmutable('now'),
            pid: \posix_getpid(),
            level: (string) $level,
            channel: $this->channel,
            message: (string) $message,
            context: [],
        );

        $this->log[] = $event;

        if (\count($this->log) >= self::MAX_FLUSH_SIZE) {
            $this->flush();
        }

        if ($this->callbackId === '') {
            $this->callbackId = EventLoop::delay(self::MAX_FLUSH_TIME, fn () => $this->flush());
        }
    }

    private function flush(): void
    {
        $log = $this->log;
        EventLoop::cancel($this->callbackId);
        $this->log = [];
        $this->callbackId = '';
        $this->messageBus->dispatch(new CompositeMessage($log));
    }
}
