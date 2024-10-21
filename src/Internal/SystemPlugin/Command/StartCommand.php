<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\Command;

use Luzrain\PHPStreamServer\Internal\Console\Command;
use Luzrain\PHPStreamServer\Internal\Console\Table;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\WorkerInfo;
use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ProcessInterface;
use Luzrain\PHPStreamServer\Server;

/**
 * @internal
 */
final class StartCommand extends Command
{
    protected const COMMAND = 'start';
    protected const DESCRIPTION = 'Start server';

    public function configure(): void
    {
        $this->options->addOptionDefinition('daemon', 'd', 'Run in daemon mode');
    }

    public function execute(array $args): int
    {
        /**
         * @var array{
         *     pidFile: string,
         *     socketFile: string,
         *     plugins: array<Plugin>,
         *     workers: array<ProcessInterface>,
         *     stopTimeout: int
         * } $args
         */

        $this->assertServerIsNotRunning($args['pidFile']);

        $daemonize = (bool) $this->options->getOption('daemon');
        $quiet = (bool) $this->options->getOption('quiet');

        $masterProcess = new MasterProcess(
            pidFile: $args['pidFile'],
            socketFile: $args['socketFile'],
            plugins: $args['plugins'],
            workers: $args['workers'],
            stopTimeout: $args['stopTimeout'],
        );

        $status = $masterProcess->masterContainer->get(ServerStatus::class);
        \assert($status instanceof ServerStatus);

        echo "❯ " . Server::TITLE . "\n";

        echo (new Table(indent: 1))
            ->addRows([
                [Server::NAME . ' version:', Server::VERSION],
                ['PHP version:', PHP_VERSION],
                ['Event loop driver:', $status->eventLoop],
                ['Workers count:', $status->getWorkersCount()],
            ])
        ;

        echo "❯ Workers\n";

        if ($status->getWorkersCount() > 0) {
            echo (new Table(indent: 1))
                ->setHeaderRow([
                    'User',
                    'Worker',
                    'Count',
                ])
                ->addRows(\array_map(static function (WorkerInfo $w) {
                    return [
                        $w->user,
                        $w->name,
                        $w->count,
                    ];
                }, $status->getWorkers()))
            ;
        } else {
            echo "  <color;bg=yellow> ! </> <color;fg=yellow>There are no workers</>\n";
        }

        if (!$daemonize) {
            echo "Press Ctrl+C to stop.\n";
        }

        return $masterProcess->run([
            'daemonize' => $daemonize,
            'quiet' => $quiet,
        ]);
    }
}
