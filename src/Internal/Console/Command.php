<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

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
}
