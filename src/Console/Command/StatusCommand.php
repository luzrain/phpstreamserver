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

        return "❯ PHPRunner - PHP application server\n" . (new Table(indent: 1))
            ->addRows([
                ['PHP version:', sprintf('%s', $status['php_version'])],
                ['PHPRunner version:', sprintf('%s', $status['phprunner_version'])],
                ['Event loop driver:', sprintf('%s', $status['event_loop'])],
                ['Loaded:', '/app/.stuff/test.php'],
                //['Status:', sprintf('<color;fg=green>%s</>', 'active (running)')],
                ['Status:', sprintf('<color;fg=red>%s</>', 'inactive')],
                ['Workers:', '2'],
                ['Processes:', '<color;fg=gray>0</>'],
                ['Memory:', '<color;fg=gray>0M</>'],
            ])
        ;
    }

    private function showWorkers(): string
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

    private function showProcesses(): string
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

    private function showConnections(): string
    {
        $t = "<fg=red;bg=test>ddd</fg>\n";
        $t .= "<fg=test>ddd2</fg>";

        return $t . "\n";
    }
}
