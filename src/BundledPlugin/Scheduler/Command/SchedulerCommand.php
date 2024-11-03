<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Command;

use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Status\PeriodicWorkerInfo;
use Luzrain\PHPStreamServer\BundledPlugin\Scheduler\Status\SchedulerStatus;
use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\ContainerGetCommand;

/**
 * @internal
 */
final class SchedulerCommand extends Command
{
    public const COMMAND = 'scheduler';
    public const DESCRIPTION = 'Show scheduler map';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $this->assertServerIsRunning($args['pidFile']);

        echo "❯ Scheduler\n";

        $bus = new SocketFileMessageBus($args['socketFile']);
        $status = $bus->dispatch(new ContainerGetCommand(SchedulerStatus::class))->await();
        \assert($status instanceof SchedulerStatus);

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
