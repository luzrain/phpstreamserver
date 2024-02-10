<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal\ProcessMessage;

/**
 * @internal
 */
interface Message
{
    public function getPid(): int;
}
