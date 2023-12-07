<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\MasterProcess;

final class StopCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'stop';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% stop';
    }

    public function run(array $arguments): never
    {
        $this->masterProcess->stop();
        exit;
    }
}
