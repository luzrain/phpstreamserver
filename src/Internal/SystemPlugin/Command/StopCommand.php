<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Exception\ServerIsShutdownException;
use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Options;

/**
 * @internal
 */
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
