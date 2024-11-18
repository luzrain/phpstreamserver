<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class ContainerSetCommand implements MessageInterface
{
    public function __construct(public string $id, public mixed $value)
    {
    }
}
