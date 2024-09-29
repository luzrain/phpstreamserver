<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Options;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\WorkerInfo;

/**
 * @internal
 */
final class WorkersCommand extends Command
{
    protected const COMMAND = 'workers';
    protected const DESCRIPTION = 'Show workers status';

    public function execute(Options $options): int
    {
        echo "❯ Workers\n";

        $status = $this->masterProcess->get(ServerStatus::class);
        \assert($status instanceof ServerStatus);

        if ($status->getWorkersCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Count',
                ])
                ->addRows(\array_map(array: $status->getWorkers(), callback: static fn(WorkerInfo $w) => [
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
