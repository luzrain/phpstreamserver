<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Process;

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
        $status = $this->masterProcess->getServerStatus();

        echo "❯ Processes\n";

        if ($status->getProcessesCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'Pid',
                    'User',
                    'Memory',
                    'Worker',
                    'Connections',
                    'Requests',
                    'Bytes (RX / TX)',
                    'Status',
                ])
                ->addRows(\array_map(array: $status->getProcesses(), callback: static function (Process $w) {
                    return [
                        $w->pid,
                        $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                        $w->memory > 0 ? Functions::humanFileSize($w->memory) : '<color;fg=gray>??</>',
                        $w->name,
                        $w->connections === 0 ? '<color;fg=gray>0</>' : $w->connections,
                        $w->requests === 0 ? '<color;fg=gray>0</>' : $w->requests,
                        $w->rx === 0 && $w->tx === 0
                            ? \sprintf('<color;fg=gray>(%s / %s)</>', Functions::humanFileSize($w->rx), Functions::humanFileSize($w->tx))
                            : \sprintf('(%s / %s)', Functions::humanFileSize($w->rx), Functions::humanFileSize($w->tx)),
                        match(true) {
                            $w->detached => '[<color;fg=cyan>DETACHED</>]',
                            $w->blocked => '[<color;fg=yellow>BLOCKED</>]',
                            default => '[<color;fg=green>OK</>]',
                        },
                    ];
                }));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no running processes</>\n";
        }

        return 0;
    }
}
