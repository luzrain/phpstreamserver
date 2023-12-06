<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\MasterProcess;

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

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% status';
    }

    public function run(array $arguments): never
    {
        echo $this->show();
        exit;
    }

    private function show(): string
    {
        $status = $this->masterProcess->getStatus();

        return ($status->isRunning ? '<color;fg=green>●</>' : '●') . " PHPRunner - PHP application server\n" . (new Table(indent: 1))
            ->addRows([
                ['PHP version:', $status->phpVersion],
                ['PHPRunner version:', $status->phpRunnerVersion],
                ['Event loop driver:', $status->eventLoop],
                ['Start file:', $status->startFile],
                ['Status:', $status->isRunning
                    ? '<color;fg=green>active</> since ' . $status->startedAt->format(\DateTimeInterface::RFC7231)
                    : '<color;fg=red>inactive</>'
                ],
                ['Workers count:', $status->workersCount],
                ['Processes count:', $status->processesCount > 0 ? $status->processesCount : '<color;fg=gray>0</>'],
                ['Memory usage:', $status->totalMemory > 0 ? $this->humanFileSize($status->totalMemory) : '<color;fg=gray>0</>'],
            ])
        ;
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "$bytes B";
        }
        $bytes = \round($bytes / 1024, 0);
        if ($bytes < 1024) {
            return "$bytes KB";
        }
        $bytes = \round($bytes / 1024, 1);
        if ($bytes < 1024) {
            return "$bytes MB";
        }
        $bytes = \round($bytes / 1024, 1);
        return "$bytes GB";
    }
}
