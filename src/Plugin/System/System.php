<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System;

use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\Plugin\System\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\ReloadCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\StartCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\StatusCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\StopCommand;
use Luzrain\PHPStreamServer\Plugin\System\Command\WorkersCommand;

final class System extends Plugin
{
    private MasterProcess $masterProcess;

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterProcess = $masterProcess;
    }

    public function commands(): array
    {
        return [
            new StartCommand($this->masterProcess),
            new StopCommand($this->masterProcess),
            new ReloadCommand($this->masterProcess),
            new StatusCommand($this->masterProcess),
            new WorkersCommand($this->masterProcess),
            new ProcessesCommand($this->masterProcess),
            new ConnectionsCommand($this->masterProcess),
        ];
    }
}
