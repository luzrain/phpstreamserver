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

    public function getCommand(): string
    {
        return 'stop';
    }

    public function getHelp(): string
    {
        return 'Stop server';
    }

    public function run(array $arguments): int
    {
        $this->masterProcess->stop();

        return 0;
    }
}
