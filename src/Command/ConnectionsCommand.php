<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Internal\MasterProcess;
use Luzrain\PhpRunner\Server\Connection\ActiveConnection;

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
                ->addRows(\array_map(array: $connections, callback: function (ActiveConnection $c) use ($pidMap) {
                    return [
                        (string) $pidMap[$c],
                        'tcp',
                        $c->localIp . ':' . $c->localPort,
                        $c->remoteIp . ':' . $c->remotePort,
                        \sprintf('(%s / %s)', Functions::humanFileSize($c->statistics->getRx()), Functions::humanFileSize($c->statistics->getTx())),
                    ];
                }));
        } else {
            echo "  <color;fg=yellow>There are no active connections</>\n";
        }

        return 0;
    }
}
