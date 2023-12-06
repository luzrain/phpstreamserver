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

final class ProcessesCommand implements Command
{
    public function __construct(
        private WorkerPool $pool,
    ) {
    }

    public function getCommand(): string
    {
        return 'processes';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% processes';
    }

    public function run(array $arguments): never
    {
        echo $this->show();
        exit;
    }

    private function show(): string
    {
        $status = (new ProcessesStatus($this->pool))->getData();

        return "❯ Processes\n" . (new Table(indent: 1))
            ->setHeaderRow([
                'Pid',
                'User',
                'Memory',
                'Worker',
                'Connections',
                'Requests',
            ])
            ->addRows($status['processes'])
        ;
    }
}
