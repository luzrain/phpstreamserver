<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Exception\ServerAlreadyRunningException;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\ServerStatus\WorkerProcessInfo;
use Luzrain\PHPStreamServer\Server;

final class StartCommand extends Command
{
    protected const COMMAND = 'start';
    protected const DESCRIPTION = 'Start server';

    public function configure(Options $options): void
    {
        $options->addOptionDefinition('daemon', 'd', 'Run in daemon mode');
    }

    public function execute(Options $options): int
    {
        $isDaemon = (bool) $options->getOption('daemon');

        /** @var ServerStatus $status */
        $status = $this->masterProcess->get(ServerStatus::class);

        echo "❯ " . Server::TITLE . "\n";
        echo (new Table(indent: 1))
            ->addRows([
                ['PHP version:', $status->phpVersion],
                [Server::NAME . ' version:', $status->version],
                ['Event loop driver:', $status->eventLoop],
                ['Workers count:', $status->getWorkersCount()],
            ])
        ;

        echo "❯ Workers\n";

        if ($status->getWorkersCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Count',
                ])
                ->addRows(\array_map(static function (WorkerProcessInfo $w) {
                    return [
                        $w->user,
                        $w->name,
                        $w->count,
                    ];
                }, $status->getWorkerProcesses()))
            ;
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no workers</>\n";
        }

        if (!$isDaemon) {
            echo "Press Ctrl+C to stop.\n";
        }

        try {
            return $this->masterProcess->run($isDaemon);
        } catch (ServerAlreadyRunningException $e) {
            echo \sprintf("<color;bg=red>%s</>\n", $e->getMessage());
            return 1;
        }
    }
}
