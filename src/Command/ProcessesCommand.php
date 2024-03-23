<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\Status\WorkerProcessStatus;

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
                    'Listen',
                    'Connections',
                    'Requests',
                    'Bytes (RX / TX)',
                ])
                ->addRows(\array_map(array: $status->processes, callback: function (WorkerProcessStatus $w) {
                    $connections = \count($w->connections ?? []);
                    $packages = $w->connectionStatistics?->getPackages() ?? 0;
                    $rx = $w->connectionStatistics?->getRx() ?? 0;
                    $tx = $w->connectionStatistics?->getTx() ?? 0;

                    return [
                        $w->pid,
                        $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                        $w->memory > 0 ? Functions::humanFileSize($w->memory) : '<color;fg=gray>??</>',
                        $w->name,
                        $w->listen ?? '<color;fg=gray>-</>',
                        $connections === 0 ? '<color;fg=gray>0</>' : $connections,
                        $packages === 0 ? '<color;fg=gray>0</>' : $packages,
                        $rx === 0 && $tx === 0
                            ? \sprintf('<color;fg=gray>(%s / %s)</>', Functions::humanFileSize($rx), Functions::humanFileSize($tx))
                            : \sprintf('(%s / %s)', Functions::humanFileSize($rx), Functions::humanFileSize($tx)),
                    ];
                }));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no running processes</>\n";
        }

        return 0;
    }
}
