<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Options;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\Scheduler\Trigger\TriggerFactory;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\PeriodicWorkerInfo;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;

/**
 * @internal
 */
final class SchedulerCommand extends Command
{
    protected const COMMAND = 'scheduler';
    protected const DESCRIPTION = 'Show scheduler map';

    public function execute(Options $options): int
    {
        echo "❯ Scheduler\n";

        if(!$this->masterProcess->isRunning()) {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>Server is not running</>\n";

            return 0;
        }

        $status = $this->masterProcess->get(ServerStatus::class);
        \assert($status instanceof ServerStatus);

        if ($status->getPeriodicTasksCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Schedule',
                    'Next run',
                    'Status',
                ])
                ->addRows(\array_map(array: $status->getPeriodicWorkers(), callback: static fn (PeriodicWorkerInfo $w) => [
                    $w->user === 'root' ? $w->user : "<color;fg=gray>{$w->user}</>",
                    $w->name,
                    $w->schedule ?: '-',
                    $w->nextRunDate?->format('Y-m-d H:i:s') ?? '<color;fg=gray>-</>',
                    match(true) {
                        $w->nextRunDate !== null => '[<color;fg=green>OK</>]',
                        default => '[<color;fg=red>ERROR</>]',
                    },
                ]));
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no scheduled tasks</>\n";
        }

        return 0;
    }
}
