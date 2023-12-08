<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\Internal\MasterProcess;
use Luzrain\PhpRunner\Status\WorkerStatus;

final class StartCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getOption(): string
    {
        return 'start';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% start [--daemon]';
    }

    public function run(array $arguments): void
    {
        $isDaemon = \in_array('-d', $arguments) || \in_array('--daemon', $arguments);
        $status = $this->masterProcess->getStatus();

        echo "❯ PHPRunner - PHP application server\n";

        echo (new Table(indent: 1))
            ->addRows([
                ['PHP version:', $status->phpVersion],
                ['PHPRunner version:', $status->phpRunnerVersion],
                ['Event loop driver:', $status->eventLoop],
                ['Workers count:', $status->workersCount],
            ])
        ;
        echo "❯ Workers\n";

        echo (new Table(indent: 1))
            ->setHeaderRow([
                'User',
                'Worker',
                'Count',
                'Listen',
            ])
            ->addRows(array_map(function (WorkerStatus $w) {
                return [
                    $w->user,
                    $w->name,
                    $w->count,
                    '-'
                ];
            }, $status->workers))
        ;

        if (!$isDaemon) {
            echo "Press Ctrl+C to stop.\n";
        }

        $this->masterProcess->run($isDaemon);
    }
}
