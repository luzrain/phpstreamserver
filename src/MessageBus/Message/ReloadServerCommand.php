<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * @implements Message<null>
 */
final readonly class ReloadServerCommand implements Message
{
    public function __construct()
    {
    }
}
