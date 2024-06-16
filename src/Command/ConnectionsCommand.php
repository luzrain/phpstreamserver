<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Server\Connection;
use Luzrain\PHPStreamServer\Server\Connection\ActiveConnection;

final class ConnectionsCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'connections';
    }

    public function getHelp(): string
    {
        return 'Show active connections';
    }

    public function run(array $arguments): int
    {
        $status = $this->masterProcess->getStatus();
        $connections = [];
        $pidMap = new \WeakMap();

        foreach ($status->processes as $process) {
            foreach ($process->connections ?? [] as $connection) {
                $connections[] = $connection;
                $pidMap[$connection] = $process->pid;
            }
        }

        echo "❯ Connections\n";

        if (\count($connections) > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'Pid',
                    'Transport',
                    'Local address',
                    'Remote address',
                    'Bytes (RX / TX)',
                ])
                ->addRows(\array_map(array: $connections, callback: function (Connection $c) use ($pidMap) {
                    return [
                        (string) $pidMap[$c],
                        'tcp',
                        $c->localIp . ':' . $c->localPort,
                        $c->remoteIp . ':' . $c->remotePort,
                        \sprintf('(%s / %s)', Functions::humanFileSize($c->rx), Functions::humanFileSize($c->tx)),
                    ];
                }));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no active connections</>\n";
        }

        return 0;
    }
}
