<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Internal\MasterProcess;
use Luzrain\PhpRunner\Status\WorkerProcessStatus;

final class ProcessesCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'processes';
    }

    public function getHelp(): string
    {
        return 'Show processes status';
    }

    public function run(array $arguments): int
    {
        $status = $this->masterProcess->getStatus();

        echo "❯ Processes\n";

        if ($status->processesCount > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'Pid',
                    'User',
                    'Memory',
                    'Worker',
                    'Connections',
                    'Requests',
                    'Bytes (RX / TX)',
                ])
                ->addRows(\array_map(array: $status->processes, callback: function (WorkerProcessStatus $w) {
                    $connections = \count($w->connections);
                    $packages = $w->connectionStatistics->getPackages();
                    $rx = $w->connectionStatistics->getRx();
                    $tx = $w->connectionStatistics->getTx();

                    return [
                        $w->pid,
                        $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                        Functions::humanFileSize($w->memory),
                        $w->name,
                        $connections === 0 ? '<color;fg=gray>0</>' : $connections,
                        $packages === 0 ? '<color;fg=gray>0</>' : $packages,
                        $rx === 0 && $tx === 0
                            ? \sprintf('<color;fg=gray>(%s / %s)</>', Functions::humanFileSize($rx), Functions::humanFileSize($tx))
                            : \sprintf('(%s / %s)', Functions::humanFileSize($rx), Functions::humanFileSize($tx)),
                    ];
                }));
        } else {
            echo "  <color;fg=yellow>There are no running processes</>\n";
        }

        return 0;
    }
}
