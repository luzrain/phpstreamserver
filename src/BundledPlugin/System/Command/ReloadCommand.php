<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Event\ReloadServerCommand;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
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

        return 0;
    }
}
