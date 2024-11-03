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
use Luzrain\PHPStreamServer\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Plugin;

/**
 * @internal
 */
final class SystemPlugin extends Plugin
{
    private ConnectionsStatus $connectionsStatus;

    public function __construct()
    {
    }

    public function init(): void
    {
        $serverStatus = new ServerStatus();
        $this->masterContainer->set(ServerStatus::class, $serverStatus);

        $this->connectionsStatus = new ConnectionsStatus();
        $this->masterContainer->set(ConnectionsStatus::class, $this->connectionsStatus);
    }

    public function start(): void
    {
        /** @var MessageHandler $handler */
        $handler = &$this->masterContainer->get('handler');

        $this->connectionsStatus->subscribeToWorkerMessages($handler);
    }

    public function commands(): array
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
