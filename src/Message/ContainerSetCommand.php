<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Message;

/**
 * @implements Message<void>
 */
final readonly class ContainerSetCommand implements Message
{
    public function __construct(public string $id, public mixed $value)
    {
    }
}
