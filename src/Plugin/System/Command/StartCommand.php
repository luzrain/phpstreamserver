<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Exception\AlreadyRunningException;
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

        /** @var \WeakReference<ServerStatus> $status */
        $status = \WeakReference::create($this->masterProcess->getServerStatus());

        echo "❯ " . Server::TITLE . "\n";
        echo (new Table(indent: 1))
            ->addRows([
                ['PHP version:', $status->get()->phpVersion],
                [Server::NAME . ' version:', $status->get()->version],
                ['Event loop driver:', $status->get()->eventLoop],
                ['Workers count:', $status->get()->getWorkersCount()],
            ])
        ;

        echo "❯ Workers\n";

        if ($status->get()->getWorkersCount() > 0) {
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
                }, $status->get()->getWorkerProcesses()))
            ;
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no workers</>\n";
        }

        if (!$isDaemon) {
            echo "Press Ctrl+C to stop.\n";
        }

        try {
            return $this->masterProcess->run($isDaemon);
        } catch (AlreadyRunningException $e) {
            echo \sprintf("<color;bg=red>%s</>\n", $e->getMessage());
            return 1;
        }
    }
}
