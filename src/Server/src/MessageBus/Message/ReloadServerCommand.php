<?php

declare(strict_types=1);

namespace PHPStreamServer\MessageBus\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class ReloadServerCommand implements MessageInterface
{
    public function __construct()
    {
    }
}
