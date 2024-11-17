<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Worker;

enum Status
{
    case SHUTDOWN;
    case STARTING;
    case RUNNING;
    case STOPPING;
}
