<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;

interface Module
{
    public function start(MasterProcess $masterProcess): void;

    public function stop(): Future|null;

    public function free(): void;
}
