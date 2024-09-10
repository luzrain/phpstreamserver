<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use function Amp\async;

trait NullStop
{
    public function stop(): Future
    {
        return async(static fn() => null);
    }
}
