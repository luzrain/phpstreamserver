<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class ContainerSetCommand implements Message
{
    public function __construct(public string $id, public mixed $value)
    {
    }
}
