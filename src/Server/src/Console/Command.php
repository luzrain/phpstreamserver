<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Console;

use Luzrain\PHPStreamServer\Internal\Console\ServerIsNotRunning;
use Luzrain\PHPStreamServer\Internal\Console\ServerIsRunning;
use function Luzrain\PHPStreamServer\Internal\isRunning;

abstract class Command
{
    /**
     * Describe command name. e.g. "start"
     */
    public const COMMAND = '';

    /**
     * Describe command name. e.g. "Start server"
     */
    public const DESCRIPTION = '';

    public Options $options;

    /**
     * Configure command.
     * Could be used to register options for command e.g. $this->options->addOptionDefinition('daemon', 'd', 'Run in daemon mode');
     */
    public function configure(): void
    {
    }

    /**
     * Execute command.
     * MUST return exit code
     */
    abstract public function execute(array $args): int;

    /**
     * Ensure the server is running. Otherwise exit with error.
     */
    final public function assertServerIsRunning(string $pidFile): void
    {
        if (!isRunning($pidFile)) {
            throw new ServerIsNotRunning();
        }
    }

    /**
     * Ensure the server is NOT running. Otherwise exit with error.
     */
    final public function assertServerIsNotRunning(string $pidFile): void
    {
        if (isRunning($pidFile)) {
            throw new ServerIsRunning();
        }
    }
}
