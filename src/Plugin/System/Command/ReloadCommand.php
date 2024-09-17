<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;
use Luzrain\PHPStreamServer\Exception\NotRunningException;

final class ReloadCommand extends Command
{
    protected const COMMAND = 'reload';
    protected const DESCRIPTION = 'Reload server';

    public function execute(Options $options): int
    {
        try {
            $this->masterProcess->reload();
        } catch (NotRunningException $e) {
            echo \sprintf("<color;bg=red>%s</>\n", $e->getMessage());
            return 1;
        }

        return 0;
    }
}
