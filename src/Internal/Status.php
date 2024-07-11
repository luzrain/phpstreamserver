<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

enum Status
{
    case STARTING;
    case RUNNING;
    case SHUTDOWN;
}
