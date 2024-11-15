<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System;

use Luzrain\PHPStreamServer\BundledPlugin\System\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\ReloadCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\StartCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\StatusCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\StopCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\WorkersCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\ConnectionsStatus;
use Luzrain\PHPStreamServer\BundledPlugin\System\Status\ServerStatus;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Plugin;

/**
 * @internal
 */
final class SystemPlugin extends Plugin
{
    public function __construct()
    {
    }

    public function onStart(): void
    {
        $serverStatus = new ServerStatus();
        $this->masterContainer->set(ServerStatus::class, $serverStatus);

        $connectionsStatus = new ConnectionsStatus();
        $this->masterContainer->set(ConnectionsStatus::class, $connectionsStatus);

        /** @var MessageHandlerInterface $handler */
        $handler = &$this->masterContainer->get('handler');

        $connectionsStatus->subscribeToWorkerMessages($handler);
    }

    public function registerCommands(): array
    {
        return [
            new StartCommand(),
            new StopCommand(),
            new ReloadCommand(),
            new StatusCommand(),
            new WorkersCommand(),
            new ConnectionsCommand(),
        ];
    }
}
