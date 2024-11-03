<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Command;

use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\ProcessInfo;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\ConnectionsStatus;
use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\ContainerGetCommand;
use function Luzrain\PHPStreamServer\Internal\humanFileSize;

/**
 * @internal
 */
final class ProcessesCommand extends Command
{
    public const COMMAND = 'processes';
    public const DESCRIPTION = 'Show processes status';

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

        $connectionsStatus = $bus->dispatch(new ContainerGetCommand(ConnectionsStatus::class))->await();
        \assert($connectionsStatus instanceof ConnectionsStatus);

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
                ->addRows(\array_map(array: $processes, callback: static function (ProcessInfo $w) use ($connectionsStatus) {
                    $c = $connectionsStatus->getProcessConnectionsInfo($w->pid);

                    return [
                        $w->pid,
                        $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                        $w->memory > 0 ? humanFileSize($w->memory) : '<color;fg=gray>??</>',
                        $w->name,
                        \count($c->connections) === 0 ? '<color;fg=gray>0</>' : \count($c->connections),
                        $c->requests === 0 ? '<color;fg=gray>0</>' : $c->requests,
                        $c->rx === 0 && $c->tx === 0
                            ? \sprintf('<color;fg=gray>(%s / %s)</>', humanFileSize($c->rx), humanFileSize($c->tx))
                            : \sprintf('(%s / %s)', humanFileSize($c->rx), humanFileSize($c->tx)),
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
