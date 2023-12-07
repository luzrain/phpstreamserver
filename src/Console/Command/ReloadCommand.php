<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\MasterProcess;

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

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% reload';
    }

    public function run(array $arguments): never
    {
        $this->masterProcess->reload();
        exit;
    }
}
