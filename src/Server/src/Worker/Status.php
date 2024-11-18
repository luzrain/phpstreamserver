<?php

declare(strict_types=1);

namespace PHPStreamServer\Worker;

enum Status
{
    case SHUTDOWN;
    case STARTING;
    case RUNNING;
    case STOPPING;
}
