<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Options;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\ServerStatus\WorkerProcessInfo;

final class WorkersCommand extends Command
{
    protected const COMMAND = 'workers';
    protected const DESCRIPTION = 'Show workers status';

    public function execute(Options $options): int
    {
        $status = $this->masterProcess->getServerStatus();

        echo "❯ Workers\n";

        if ($status->getWorkersCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Count',
                ])
                ->addRows(\array_map(array: $status->getWorkerProcesses(), callback: static fn(WorkerProcessInfo $w) => [
                    $w->user,
                    $w->name,
                    $w->count,
                ]));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no workers</>\n";
        }

        return 0;
    }
}
