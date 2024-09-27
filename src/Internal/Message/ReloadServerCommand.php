<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<bool>
 */
final readonly class ReloadServerCommand implements Message
{
    public function __construct()
    {
    }
}
