<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Options;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\RunningProcess;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;

/**
 * @internal
 */
final class ProcessesCommand extends Command
{
    protected const COMMAND = 'processes';
    protected const DESCRIPTION = 'Show processes status';

    public function execute(Options $options): int
    {
        echo "❯ Processes\n";

        if(!$this->masterProcess->isRunning()) {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>Server is not running</>\n";

            return 0;
        }

        $status = $this->masterProcess->get(ServerStatus::class);
        \assert($status instanceof ServerStatus);

        if ($status->getProcessesCount() > 0) {
            $processes = $status->getProcesses();
            \usort($processes, static fn (RunningProcess $a, RunningProcess $b) => $a->workerId <=> $b->workerId);

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
                ->addRows(\array_map(array: $processes, callback: static function (RunningProcess $w) {
                    return [
                        $w->pid,
                        $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                        $w->memory > 0 ? Functions::humanFileSize($w->memory) : '<color;fg=gray>??</>',
                        $w->name,
                        \count($w->connections) === 0 ? '<color;fg=gray>0</>' : \count($w->connections),
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
