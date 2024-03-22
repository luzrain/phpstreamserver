<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ProcessMessage;

/**
 * @internal
 */
interface Message
{
    public function getPid(): int;
}
