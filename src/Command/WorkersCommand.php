<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Command;

use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\Console\Table;
use Luzrain\PhpRunner\MasterProcess;
use Luzrain\PhpRunner\Status\WorkerStatus;

final class WorkersCommand implements Command
{
    public function __construct(
        private MasterProcess $masterProcess,
    ) {
    }

    public function getOption(): string
    {
        return 'workers';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% workers';
    }

    public function run(array $arguments): void
    {
        $status = $this->masterProcess->getStatus();

        echo "❯ Workers\n";

        echo (new Table(indent: 1))
            ->setHeaderRow([
                'User',
                'Worker',
                'Count',
                'Listen',
            ])
            ->addRows(\array_map(array: $status->workers, callback: fn (WorkerStatus $w) => [
                $w->user,
                $w->name,
                $w->count,
                '-',
            ]));
    }
}
