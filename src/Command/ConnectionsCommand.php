<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;

final class ConnectionsCommand implements Command
{
    public function __construct()
    {
    }

    public function getCommand(): string
    {
        return 'connections';
    }

    public function getHelp(): string
    {
        return 'Show active connections';
    }

    public function run(array $arguments): int
    {
        echo "TODO\n";

        return 0;
    }
}
