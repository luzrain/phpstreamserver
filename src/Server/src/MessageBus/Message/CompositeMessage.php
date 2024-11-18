<?php

declare(strict_types=1);

namespace PHPStreamServer\MessageBus\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * Used to send multiple messages in one request.
 * @implements MessageInterface<void>
 */
final readonly class CompositeMessage implements MessageInterface
{
    public function __construct(
        /**
         * @var iterable<MessageInterface>
         */
        public iterable $messages,
    ) {
    }
}
