<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * @implements Message<mixed>
 */
final readonly class ContainerGetCommand implements Message
{
    public function __construct(public string $id)
    {
    }
}
