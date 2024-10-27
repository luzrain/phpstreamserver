<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Event\StopServerCommand;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Server;

/**
 * @internal
 */
final class StopCommand extends Command
{
    protected const COMMAND = 'stop';
    protected const DESCRIPTION = 'Stop server';

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
