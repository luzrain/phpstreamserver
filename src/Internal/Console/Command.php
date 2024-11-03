<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

use function Luzrain\PHPStreamServer\Internal\isRunning;

/**
 * @internal
 */
abstract class Command
{
    protected const COMMAND = '';
    protected const DESCRIPTION = '';

    public Options $options;

    public static function getCommand(): string
    {
        return static::COMMAND;
    }

    public static function getDescription(): string
    {
        return static::DESCRIPTION;
    }

    public function configure(): void
    {
    }

    abstract public function execute(array $args): int;

    final public function assertServerIsRunning(string $pidFile): void
    {
        if (!isRunning($pidFile)) {
            throw new ServerIsNotRunning();
        }
    }

    final public function assertServerIsNotRunning(string $pidFile): void
    {
        if (isRunning($pidFile)) {
            throw new ServerIsRunning();
        }
    }
}
