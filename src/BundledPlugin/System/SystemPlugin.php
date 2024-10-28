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
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Plugin\Plugin;

/**
 * @internal
 */
final class SystemPlugin extends Plugin
{
    private Container $masterContainer;
    private ConnectionsStatus $connectionsStatus;

    public function __construct()
    {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterContainer = $masterProcess->masterContainer;

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
