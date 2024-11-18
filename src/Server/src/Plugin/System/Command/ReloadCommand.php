<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Server\src\MessageBus\SocketFileMessageBus;
use PHPStreamServer\Console\Command;
use PHPStreamServer\MessageBus\Message\ReloadServerCommand;
use PHPStreamServer\Server;

/**
 * @internal
 */
final class ReloadCommand extends Command
{
    public const COMMAND = 'reload';
    public const DESCRIPTION = 'Reload server';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        $bus = new SocketFileMessageBus($args['socketFile']);
        echo Server::NAME ." reloading ...\n";
        $bus->dispatch(new ReloadServerCommand())->await();

        return 0;
    }
}
