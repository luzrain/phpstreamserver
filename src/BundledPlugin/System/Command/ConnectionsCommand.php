<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Command;

use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\Connection;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\ConnectionsStatus;
use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\Event\ContainerGetCommand;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;

/**
 * @internal
 */
final class ConnectionsCommand extends Command
{
    protected const COMMAND = 'connections';
    protected const DESCRIPTION = 'Show active connections';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        echo "❯ Connections\n";

        $bus = new SocketFileMessageBus($args['socketFile']);

        $connectionsStatus = $bus->dispatch(new ContainerGetCommand(ConnectionsStatus::class))->await();
        \assert($connectionsStatus instanceof ConnectionsStatus);

        $connections = $connectionsStatus->getActiveConnections();

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
