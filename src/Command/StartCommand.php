<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Worker;
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
                ->addRows(\array_map(static function (Worker $w) {
                    return [
                        $w->user,
                        $w->name,
                        $w->count,
                    ];
                }, $status->get()->getWorkers()))
            ;
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no workers</>\n";
        }

        if (!$isDaemon) {
            echo "Press Ctrl+C to stop.\n";
        }

        return $this->masterProcess->run($isDaemon);
    }
}
