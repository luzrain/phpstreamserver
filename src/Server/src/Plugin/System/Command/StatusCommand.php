<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Command;

use Luzrain\PHPStreamServer\Console\Command;
use Luzrain\PHPStreamServer\Console\Table;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\Message\ContainerGetCommand;
use Luzrain\PHPStreamServer\Plugin\Supervisor\Status\SupervisorStatus;
use Luzrain\PHPStreamServer\Plugin\System\Status\ServerStatus;
use Luzrain\PHPStreamServer\Server;
use function Luzrain\PHPStreamServer\Internal\getDriverName;
use function Luzrain\PHPStreamServer\Internal\getStartFile;
use function Luzrain\PHPStreamServer\Internal\humanFileSize;
use function Luzrain\PHPStreamServer\Internal\isRunning;

/**
 * @internal
 */
final class StatusCommand extends Command
{
    public const COMMAND = 'status';
    public const DESCRIPTION = 'Show server status';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $isRunning = isRunning($args['pidFile']);
        $eventLoop = getDriverName();
        $startFile = getStartFile();

        if ($isRunning) {
            $bus = new SocketFileMessageBus($args['socketFile']);
            $serverStatus = $bus->dispatch(new ContainerGetCommand(ServerStatus::class))->await();
            \assert($serverStatus instanceof ServerStatus);
            $supervosorStatus = $bus->dispatch(new ContainerGetCommand(SupervisorStatus::class))->await();
            \assert($supervosorStatus instanceof SupervisorStatus);
            $startedAt = $serverStatus->startedAt;
            $workersCount = $supervosorStatus->getWorkersCount();
            $processesCount = $supervosorStatus->getProcessesCount();
            $totalMemory = $supervosorStatus->getTotalMemory();
        }

        echo ($isRunning ? '<color;fg=green>●</> ' : '● ') . Server::TITLE . "\n";

        $rows = [
            [Server::NAME . ' version:', Server::getVersion()],
            ['PHP version:', PHP_VERSION],
            ['Event loop driver:', $eventLoop],
            ['Start file:', $startFile],
            ['Status:', $isRunning
                ? '<color;fg=green>active</> since ' . $startedAt->format(\DateTimeInterface::RFC7231)
                : 'inactive',
            ],
        ];

        if ($isRunning) {
            $rows = [...$rows, ...[
                ['Workers count:', $workersCount],
                ['Processes count:', $processesCount],
                ['Memory usage:', humanFileSize($totalMemory)],
            ]];
        }

        echo (new Table(indent: 1))->addRows($rows);

        return 0;
    }
}
