<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Connection;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;

final class ConnectionsCommand extends Command
{
    protected const COMMAND = 'connections';
    protected const DESCRIPTION = 'Show active connections';

    public function execute(Options $options): int
    {
        if(!$this->masterProcess->isRunning()) {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>Server is not running</>\n";

            return 0;
        }

        $status = $this->masterProcess->get(ServerStatus::class);
        \assert($status instanceof ServerStatus);
        $connections = [];

        foreach ($status->getProcesses() as $process) {
            \array_push($connections, ...$process->connections);
        }

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
