<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin;

use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
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
    private MasterProcess $masterProcess;
    private ServerStatus $serverStatus;

    public function __construct()
    {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterProcess = $masterProcess;

        if (!$this->masterProcess->isRunning()) {
            $this->serverStatus = new ServerStatus();
            $this->masterProcess->masterContainer->set(ServerStatus::class, $this->serverStatus);
        }
    }

    public function start(): void
    {
        /** @var MessageHandler $handler */
        $handler = $this->masterProcess->masterContainer->get('handler');

        $this->serverStatus->setRunning();
        $this->serverStatus->subscribeToWorkerMessages($handler);
    }

    public function commands(): array
    {
        return [
            new StartCommand(),
//            new StopCommand($this->masterProcess),
//            new ReloadCommand($this->masterProcess),
//            new StatusCommand($this->masterProcess),
//            new WorkersCommand($this->masterProcess),
//            new ProcessesCommand($this->masterProcess),
//            new ConnectionsCommand($this->masterProcess),
        ];
    }
}
