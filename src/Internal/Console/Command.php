<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

use Luzrain\PHPStreamServer\MasterProcess;

/**
 * @internal
 */
abstract class Command
{
    protected const COMMAND = '';
    protected const DESCRIPTION = '';

    public function __construct(protected readonly MasterProcess $masterProcess)
    {
    }

    public static function getCommand(): string
    {
        return static::COMMAND;
    }

    public static function getDescription(): string
    {
        return static::DESCRIPTION;
    }

    public function configure(Options $options): void
    {
    }

    abstract public function execute(Options $options): int;
}
