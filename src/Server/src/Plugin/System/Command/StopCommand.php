<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\StopServerCommand;
use Luzrain\PHPStreamServer\Server;

/**
 * @internal
 */
final class StopCommand extends Command
{
    public const COMMAND = 'stop';
    public const DESCRIPTION = 'Stop server';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        $bus = new SocketFileMessageBus($args['socketFile']);
        echo Server::NAME ." stopping ...\n";
        $bus->dispatch(new StopServerCommand())->await();
        echo Server::NAME ." stopped\n";

        return 0;
    }
}
