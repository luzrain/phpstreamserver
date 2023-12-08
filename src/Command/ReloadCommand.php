<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Internal\MasterProcess;

final class ReloadCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getOption(): string
    {
        return 'reload';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% reload';
    }

    public function run(array $arguments): void
    {
        $this->masterProcess->reload();
    }
}
