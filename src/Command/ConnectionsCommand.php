<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;

final class ConnectionsCommand implements Command
{
    public function __construct()
    {
    }

    public function getOption(): string
    {
        return 'connections';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% connections';
    }

    public function run(array $arguments): int
    {
        echo "TODO\n";

        return 0;
    }
}
