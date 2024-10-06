<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\Console\App;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\System;
use Luzrain\PHPStreamServer\Plugin\Plugin;

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
        );

        $this->app = new App();
        $this->addPlugin(new System());
    }

    public function addPlugin(Plugin ...$plugins): self
    {
        foreach ($plugins as $plugin) {
            $this->masterProcess->addPlugin($plugin);
            $commands = $plugin->commands();
            \array_walk($commands, $this->app->register(...));
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
