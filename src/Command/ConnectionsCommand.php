<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Connection;

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
        $connections = $this->masterProcess->getServerConnections();

        echo "❯ Connections\n";

        if (\count($connections) > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'Pid',
                    'Local address',
                    'Remote address',
                    'Bytes (RX / TX)',
                ])
                ->addRows(\array_map(array: $connections, callback: static function (Connection $c) {
                    return [
                        $c->pid,
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
