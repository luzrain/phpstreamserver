<?php

declare(strict_types=1);

namespace PHPStreamServer\MessageBus\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<bool>
 */
final readonly class ContainerHasCommand implements MessageInterface
{
    public function __construct(public string $id)
    {
    }
}
