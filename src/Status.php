<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

enum Status
{
    case SHUTDOWN;
    case STARTING;
    case RUNNING;
    case STOPPING;
}
