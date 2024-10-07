<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Message;

/**
 * @implements Message<bool>
 */
final readonly class ReloadServerCommand implements Message
{
    public function __construct()
    {
    }
}