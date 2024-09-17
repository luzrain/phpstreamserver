<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;

final class StopCommand extends Command
{
    protected const COMMAND = 'stop';
    protected const DESCRIPTION = 'Stop server';

    public function execute(Options $options): int
    {
        // TODO: throw excepton if server already runs
        $this->masterProcess->stop();

        return 0;
    }
}
