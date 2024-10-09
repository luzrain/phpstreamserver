<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

/**
 * Used to send multiple messages in one request.
 * @implements Message<void>
 */
final readonly class CompositeMessage implements Message
{
    public function __construct(
        /**
         * @var array<Message>
         */
        public array $messages,
    ) {
    }
}
