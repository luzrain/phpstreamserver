<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * Used to send multiple messages in one request.
 * @implements Message<void>
 */
final readonly class CompositeMessage implements Message
{
    public function __construct(
        /**
         * @var iterable<Message>
         */
        public iterable $messages,
    ) {
    }
}
