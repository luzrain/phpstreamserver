<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\RunnableProcess;

interface PeriodicProcessInterface extends ProcessInterface, RunnableProcess
{
}
