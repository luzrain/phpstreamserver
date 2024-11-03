<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\ReloadServerCommand;
use Luzrain\PHPStreamServer\Server;

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
