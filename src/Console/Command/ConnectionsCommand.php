<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Config;
use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\MasterProcess;
use Luzrain\PhpRunner\PhpRunner;
use Luzrain\PhpRunner\Status\ProcessesStatus;
use Luzrain\PhpRunner\Status\MasterProcessStatus;
use Luzrain\PhpRunner\Status\WorkersStatus;
use Luzrain\PhpRunner\WorkerPool;
use Luzrain\PhpRunner\WorkerProcess;
use Psr\Log\LoggerInterface;

final class ConnectionsCommand implements Command
{
    public function __construct(
    ) {
    }

    public function getCommand(): string
    {
        return 'connections';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% connections';
    }

    public function run(array $arguments): never
    {
        echo $this->show();
        exit;
    }

    private function show(): string
    {
        $t = "<fg=red;bg=test>ddd</fg>\n";
        $t .= "<fg=test>ddd2</fg>";

        return $t . "\n";
    }
}
