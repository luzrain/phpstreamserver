<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Internal\MasterProcess;

final class StatusJsonCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'status-json';
    }

    public function getHelp(): string
    {
        return 'Print status info in json format';
    }

    public function run(array $arguments): int
    {
        $status = $this->masterProcess->getServerStatus();
        echo \json_encode($status);

        return 0;
    }
}
