<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Command;

use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\ProcessInfo;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\Event\ContainerGetCommand;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;

/**
 * @internal
 */
final class ProcessesCommand extends Command
{
    protected const COMMAND = 'processes';
    protected const DESCRIPTION = 'Show processes status';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        echo "❯ Processes\n";

        $bus = new SocketFileMessageBus($args['socketFile']);

        $processesStatus = $bus->dispatch(new ContainerGetCommand(SupervisorStatus::class))->await();
        \assert($processesStatus instanceof SupervisorStatus);

        if ($processesStatus->getProcessesCount() > 0) {
            $processes = $processesStatus->getProcesses();
            \usort($processes, static fn (ProcessInfo $a, ProcessInfo $b) => $a->workerId <=> $b->workerId);

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
                ->addRows(\array_map(array: $processes, callback: static function (ProcessInfo $w) {
                    return [
                        $w->pid,
                        $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                        $w->memory > 0 ? Functions::humanFileSize($w->memory) : '<color;fg=gray>??</>',
                        $w->name,
                        //\count($w->connections) === 0 ? '<color;fg=gray>0</>' : \count($w->connections),
                        '111111',
                        //$w->requests === 0 ? '<color;fg=gray>0</>' : $w->requests,
                        '0',
                        '11',
//                        $w->rx === 0 && $w->tx === 0
//                            ? \sprintf('<color;fg=gray>(%s / %s)</>', Functions::humanFileSize($w->rx), Functions::humanFileSize($w->tx))
//                            : \sprintf('(%s / %s)', Functions::humanFileSize($w->rx), Functions::humanFileSize($w->tx)),
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
