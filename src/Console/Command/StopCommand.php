<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Console\Command;

final class StopCommand implements Command
{
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
        echo "Stop all\n";
        exit;
    }
}
