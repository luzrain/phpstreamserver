<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Internal\MasterProcess;

final class StopCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getOption(): string
    {
        return 'stop';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% stop';
    }

    public function run(array $arguments): void
    {
        $this->masterProcess->stop();
    }
}
