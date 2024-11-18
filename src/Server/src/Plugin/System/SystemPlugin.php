<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\System;

use PHPStreamServer\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin;
use PHPStreamServer\Plugin\System\Command\ConnectionsCommand;
use PHPStreamServer\Plugin\System\Command\ReloadCommand;
use PHPStreamServer\Plugin\System\Command\StartCommand;
use PHPStreamServer\Plugin\System\Command\StatusCommand;
use PHPStreamServer\Plugin\System\Command\StopCommand;
use PHPStreamServer\Plugin\System\Command\WorkersCommand;
use PHPStreamServer\Plugin\System\Connections\ConnectionsStatus;
use PHPStreamServer\Plugin\System\Status\ServerStatus;

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
