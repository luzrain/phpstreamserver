<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Console\App;
use Luzrain\PHPStreamServer\Internal\Logger\Logger;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\Plugin\System\System;

final class Server
{
    public const VERSION = '0.2.2';
    public const VERSION_STRING = 'phpstreamserver/' . self::VERSION;
    public const NAME = 'PHPStreamServer';
    public const TITLE = '🌸 PHPStreamServer - PHP application server';

    private App $app;
    private MasterProcess $masterProcess;

    public function __construct(
        /**
         * Defines a file that will store the process ID of the main process.
         */
        string|null $pidFile = null,

        /**
         * Timeout in seconds that master process will be waiting before force kill child processes after sending stop command.
         */
        int $stopTimeout = 6,
    ) {
        $this->masterProcess = new MasterProcess(
            pidFile: $pidFile,
            stopTimeout: $stopTimeout,
            logger: new Logger(null),
        );

        $this->app = new App();
        $this->addPlugin(new System());
    }

    public function addPlugin(Plugin ...$plugins): self
    {
        foreach ($plugins as $plugin) {
            $this->masterProcess->addPlugin($plugin);
            foreach ($plugin->commands() as $command) {
                $this->app->register($command);
            }
        }

        return $this;
    }

    public function addWorker(ProcessInterface ...$workers): self
    {
        $this->masterProcess->addWorker(...$workers);

        return $this;
    }

    public function run(): int
    {
        return $this->app->run();
    }
}
