<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\System\Command;

use PHPStreamServer\Console\Command;
use PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use PHPStreamServer\MessageBus\Message\StopServerCommand;
use PHPStreamServer\Server;

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
