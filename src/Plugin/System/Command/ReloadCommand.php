<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;

final class ReloadCommand extends Command
{
    protected const COMMAND = 'reload';
    protected const DESCRIPTION = 'Reload server';

    public function execute(Options $options): int
    {
        $this->masterProcess->reload();

        return 0;
    }
}
