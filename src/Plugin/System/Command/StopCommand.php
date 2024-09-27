<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;
use Luzrain\PHPStreamServer\Exception\ServerIsShutdownException;

final class StopCommand extends Command
{
    protected const COMMAND = 'stop';
    protected const DESCRIPTION = 'Stop server';

    public function execute(Options $options): int
    {
        try {
            $this->masterProcess->stop();
        } catch (ServerIsShutdownException $e) {
            echo \sprintf("<color;bg=red>%s</>\n", $e->getMessage());
            return 1;
        }

        return 0;
    }
}
