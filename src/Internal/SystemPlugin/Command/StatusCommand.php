<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Message\ContainerGetCommand;
use Luzrain\PHPStreamServer\Server;
use Revolt\EventLoop\DriverFactory;

/**
 * @internal
 */
final class StatusCommand extends Command
{
    protected const COMMAND = 'status';
    protected const DESCRIPTION = 'Show server status';

    public function execute(array $args): int
    {
        /**
         * @var array{pidFile: string, socketFile: string} $args
         */

        $isRunning = Functions::isRunning($args['pidFile']);
        $eventLoop = (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
        $startFile = Functions::getStartFile();

        if ($isRunning) {
            $bus = new SocketFileMessageBus($args['socketFile']);
            $status = $bus->dispatch(new ContainerGetCommand(ServerStatus::class))->await();
            \assert($status instanceof ServerStatus);
            $startedAt = $status->startedAt;
            $workersCount = $status->getWorkersCount();
            $processesCount = $status->getProcessesCount();
            $totalMemory = $status->getTotalMemory();
        }

        echo ($isRunning ? '<color;fg=green>●</> ' : '● ') . Server::TITLE . "\n";

        $rows = [
            [Server::NAME . ' version:', Server::VERSION],
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
                ['Memory usage:', Functions::humanFileSize($totalMemory)],
            ]];
        }

        echo (new Table(indent: 1))->addRows($rows);

        return 0;
    }
}
