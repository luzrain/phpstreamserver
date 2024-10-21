<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Message\ReloadServerCommand;
use Luzrain\PHPStreamServer\Server;

/**
 * @internal
 */
final class ReloadCommand extends Command
{
    protected const COMMAND = 'reload';
    protected const DESCRIPTION = 'Reload server';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        $bus = new SocketFileMessageBus($args['socketFile']);
        echo Server::NAME ." reloading ...\n";
        $bus->dispatch(new ReloadServerCommand())->await();
        echo Server::NAME ." reloaded\n";

        return 0;
    }
}
