<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Internal\MasterProcess;
use Luzrain\PhpRunner\PhpRunner;

final class StatusCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getCommand(): string
    {
        return 'status';
    }

    public function getHelp(): string
    {
        return 'Show server status';
    }

    public function run(array $arguments): int
    {
        $status = $this->masterProcess->getStatus();

        echo ($status->isRunning ? '<color;fg=green>●</> ' : '● ') . PhpRunner::TITLE . "\n";

        echo (new Table(indent: 1))
            ->addRows([
                [PhpRunner::NAME . ' version:', $status->version],
                ['PHP version:', $status->phpVersion],
                ['Event loop driver:', $status->eventLoop],
                ['Start file:', $status->startFile],
                ['Status:', $status->isRunning
                    ? '<color;fg=green>active</> since ' . ($status->startedAt?->format(\DateTimeInterface::RFC7231) ?? '?')
                    : 'inactive',
                ],
                ['Workers count:', $status->workersCount],
                ['Processes count:', $status->processesCount > 0 ? $status->processesCount : '<color;fg=gray>0</>'],
                ['Memory usage:', $status->totalMemory > 0 ? Functions::humanFileSize($status->totalMemory) : '<color;fg=gray>0</>'],
            ]);

        return 0;
    }
}
