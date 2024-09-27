<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class ContainerHasCommand implements Message
{
    public function __construct(public string $id)
    {
    }
}
