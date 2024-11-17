<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\ContainerGetCommand;
use Luzrain\PHPStreamServer\Plugin\System\Connections\Connection;
use Luzrain\PHPStreamServer\Plugin\System\Connections\ConnectionsStatus;
use function Luzrain\PHPStreamServer\Internal\humanFileSize;

/**
 * @internal
 */
final class ConnectionsCommand extends Command
{
    public const COMMAND = 'connections';
    public const DESCRIPTION = 'Show active connections';

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
                        \sprintf('(%s / %s)', humanFileSize($c->rx), humanFileSize($c->tx)),
                    ];
                }));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no active connections</>\n";
        }

        return 0;
    }
}
