<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

/**
 * @internal
 */
enum Status
{
    case SHUTDOWN;
    case STARTING;
    case RUNNING;
    case STOPPING;
}
