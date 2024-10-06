<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Message\LogEntryEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final readonly class WorkerLogger implements LoggerInterface
{
    use LoggerTrait;

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

        $this->messageBus->dispatch($event);
    }
}
