<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin;

use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageHandler;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\ReloadCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\StartCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\StatusCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\StopCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\Command\WorkersCommand;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;

/**
 * @internal
 */
final class System extends Plugin
{
    private Container $masterContainer;
    private ServerStatus $serverStatus;

    public function __construct()
    {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->serverStatus = new ServerStatus();
        $this->masterContainer = $masterProcess->masterContainer;
        $this->masterContainer->set(ServerStatus::class, $this->serverStatus);
    }

    public function start(): void
    {
        /** @var MessageHandler $handler */
        $handler = $this->masterContainer->get('handler');
        $this->serverStatus->subscribeToWorkerMessages($handler);
    }

    public function commands(): array
    {
        return [
            new StartCommand(),
            new StopCommand(),
            new ReloadCommand(),
            new StatusCommand(),
            new WorkersCommand(),
            new ProcessesCommand(),
            new ConnectionsCommand(),
        ];
    }
}
