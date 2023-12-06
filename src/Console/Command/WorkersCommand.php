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

final class WorkersCommand implements Command
{
    public function __construct(
        private WorkerPool $pool,
    ) {
    }

    public function getCommand(): string
    {
        return 'workers';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% workers';
    }

    public function run(array $arguments): never
    {
        echo $this->show();
        exit;
    }

    private function show(): string
    {
        $status = (new WorkersStatus($this->pool))->getData();

        return "❯ Workers\n" . (new Table(indent: 1))
            ->setHeaderRow([
                'User',
                'Worker',
                'Count',
                'Listen',
            ])
            ->addRows($status)
        ;
    }
}
