<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Console\Command;
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

        dump($status);

        return '';

//        return "<color;fg=green>●</> PHPRunner - PHP application server\n" . (new Table(indent: 1))
//            ->addRows([
//                ['PHP version:', $status['php_version']],
//                ['PHPRunner version:', $status['phprunner_version']],
//                ['Event loop driver:', $status['event_loop']],
//                ['Start file:', $status['start_file']],
//                //['Status:', sprintf('<color;fg=red>%s</>', 'inactive')],
//                ['Status:', sprintf('<color;fg=green>%s</> since Tue 2023-11-28 06:18:54 UTC', 'active')],
//                ['Workers count:', $status['workers_count']],
//                ['Processes count:', '<color;fg=gray>0</>'],
//                ['Memory usage:', '<color;fg=gray>0M</>'],
//            ])
//        ;
    }
}
