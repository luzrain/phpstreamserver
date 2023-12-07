<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\MasterProcess;
use Luzrain\PhpRunner\Status\WorkerStatus;

final class WorkersCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
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
        $status = $this->masterProcess->getStatus();

        return "❯ Workers\n" . (new Table(indent: 1))
            ->setHeaderRow([
                'User',
                'Worker',
                'Count',
                'Listen',
            ])
            ->addRows(array_map(function (WorkerStatus $workerStatus) {
                return [
                    $workerStatus->user,
                    $workerStatus->name,
                    $workerStatus->count,
                    '-'
                ];
            }, $status->workers))
        ;
    }
}
