<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\Status\WorkerStatus;
use Luzrain\PHPStreamServer\Server;

final class StartCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'start';
    }

    public function getHelp(): string
    {
        return 'Start server';
    }

    public function run(array $arguments): int
    {
        $isDaemon = \in_array('-d', $arguments, true) || \in_array('--daemon', $arguments, true);
        $status = $this->masterProcess->getStatus();

        echo "❯ " . Server::TITLE . "\n";
        echo (new Table(indent: 1))
            ->addRows([
                ['PHP version:', $status->phpVersion],
                [Server::NAME . ' version:', $status->version],
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
            ])
            ->addRows(\array_map(function (WorkerStatus $w) {
                return [
                    $w->user,
                    $w->name,
                    $w->count,
                ];
            }, $status->workers))
        ;

        if (!$isDaemon) {
            echo "Press Ctrl+C to stop.\n";
        }

        return $this->masterProcess->run($isDaemon);
    }
}
