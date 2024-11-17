<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System;

use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Plugin;
use Luzrain\PHPStreamServer\Plugin\System\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\ReloadCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\StartCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\StatusCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\StopCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\WorkersCommand;
use Luzrain\PHPStreamServer\Plugin\System\Connections\ConnectionsStatus;
use Luzrain\PHPStreamServer\Plugin\System\Status\ServerStatus;

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
