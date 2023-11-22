<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Config;
use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\MasterProcess;
use Luzrain\PhpRunner\PhpRunner;
use Luzrain\PhpRunner\Status\ProcessesStatus;
use Luzrain\PhpRunner\Status\Status;
use Luzrain\PhpRunner\Status\WorkersStatus;
use Luzrain\PhpRunner\WorkerPool;
use Luzrain\PhpRunner\WorkerProcess;
use Psr\Log\LoggerInterface;

final class StatusCommand implements Command
{
    public function __construct(
        private WorkerPool $pool,
    ) {
    }

    public function getCommand(): string
    {
        return 'status';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% status [--workers|--processes|--connections]';
    }

    public function run(array $arguments): never
    {
        echo $this->showStatus();

        if (in_array('--workers', $arguments)) {
             echo "\n" . $this->showWorkers();
        }

        if (in_array('--processes', $arguments)) {
            echo "\n" . $this->showProcesses();
        }

        if (in_array('--connections', $arguments)) {
            echo "\n" . $this->showConnections();
        }

        exit;
    }

    private function showStatus(): string
    {
        $status = (new Status())->getData();

        return (string) (new Table(border: false))
            ->setHeaderTitle('Status')
            ->addRows([
                ['PHP version:', $status['php_version']],
                ['PHPRunner version:', $status['phprunner_version']],
                ['Event loop driver:', $status['event_loop']],
                ['Status:', $status['status']],
            ])
        ;
    }

    private function showWorkers(): string
    {
        $status = (new WorkersStatus($this->pool))->getData();

        return (string) (new Table(border: false))
            ->setHeaderTitle('Workers')
            ->setHeaders([
                'User',
                'Worker',
                'Count',
                'Listen',
            ])
            ->addRows($status)
        ;
    }

    private function showProcesses(): string
    {
        $status = (new ProcessesStatus($this->pool))->getData();

        return (string) (new Table(border: false))
            ->setHeaderTitle('Workers')
            ->setHeaders([
                'User',
                'Pid',
                'Memory',
                'Worker',
                'Connections',
                'Requests',
            ])
            ->addRows($status['processes'])
            ->addRow(Table::SEPARATOT)
            ->addRow([
                'Total:',
                //"\033[47;30m" . 'Total:' . "\033[0m",
                '',
                '0M',
                '',
                '0',
                '0',
            ])
        ;
    }

    private function showConnections(): string
    {
        return "TODO\n";
    }
}
