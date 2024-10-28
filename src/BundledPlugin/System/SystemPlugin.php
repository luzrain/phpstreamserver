<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System;

use Luzrain\PHPStreamServer\BundledPlugin\System\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\ReloadCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\StartCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\StatusCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\StopCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Command\WorkersCommand;
use Luzrain\PHPStreamServer\BundledPlugin\System\Status\ServerStatus;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;

/**
 * @internal
 */
final class SystemPlugin extends Plugin
{
    private Container $masterContainer;

    public function __construct()
    {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterContainer = $masterProcess->masterContainer;

        $serverStatus = new ServerStatus();
        $this->masterContainer->set(ServerStatus::class, $serverStatus);
    }

    public function start(): void
    {
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
