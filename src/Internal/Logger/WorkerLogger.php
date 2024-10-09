<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

use Luzrain\PHPStreamServer\Internal\MessageBus\CompositeMessage;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Message\LogEntryEvent;
use Psr\Log\LoggerInterface;
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
    private string|null $callbackId = null;

    public function __construct(private MessageBus $messageBus)
    {
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $event = new LogEntryEvent(
            level: (string) $level,
            channel: 'app',
            message: (string) $message,
            context: ContextNormalizer::normalize($context),
        );

        $this->log[] = $event;

        if (\count($this->log) >= self::MAX_FLUSH_SIZE) {
            $this->flush();
        }

        if ($this->callbackId === null) {
            $this->callbackId = EventLoop::delay(self::MAX_FLUSH_TIME, fn () => $this->flush());
        }
    }

    private function flush(): void
    {
        $log = $this->log;
        $this->log = [];
        EventLoop::cancel($this->callbackId);
        $this->callbackId = null;
        $this->messageBus->dispatch(new CompositeMessage($log));
    }
}
