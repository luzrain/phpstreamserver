<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<bool>
 */
final readonly class StopServerCommand implements Message
{
    public function __construct(public int $code = 0)
    {
    }
}
