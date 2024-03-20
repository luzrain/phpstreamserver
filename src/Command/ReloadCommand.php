<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Internal\MasterProcess;

final class ReloadCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'reload';
    }

    public function getHelp(): string
    {
        return 'Reload server';
    }

    public function run(array $arguments): int
    {
        $this->masterProcess->reload();

        return 0;
    }
}
