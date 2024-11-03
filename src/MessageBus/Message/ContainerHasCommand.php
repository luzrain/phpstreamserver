<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<bool>
 */
final readonly class ContainerHasCommand implements MessageInterface
{
    public function __construct(public string $id)
    {
    }
}
