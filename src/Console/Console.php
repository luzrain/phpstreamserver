<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console;

use Luzrain\PhpRunner\Internal\Functions;

/**
 * @internal
 */
final class Console
{
    private array $commands;

    public function __construct(Command ...$commands)
    {
        $this->commands = $commands;
    }

    public function run(string $cmd = ''): never
    {
        [$option, $arguments] = $this->parseCommand($cmd);

        if (\in_array('-h', $arguments) || \in_array('--help', $arguments)) {
            $this->showHelp();
            exit;
        }

        foreach ($this->commands as $command) {
            if ($command->getCommand() === $option) {
                $command->run($arguments);
            }
        }

        $this->showHelp();
        exit;
    }

    /**
     * @param string $command
     * @return array{string, array}
     */
    private function parseCommand(string $command): array
    {
        if ($command === '') {
            $argv = $_SERVER['argv'];
            unset($argv[0]);
            $command = \implode(' ', $argv);
        }

        $parts = \explode(' ', $command, 2);
        return [$parts[0] ?? '', \array_filter(\explode(' ', $parts[1] ?? ''))];
    }

    private function showHelp(): void
    {
        echo "<color;fg=green>Usage:</>\n";
        foreach ($this->commands as $command) {
            echo '  ' . str_replace(['%php_bin%', '%start_file%'], [PHP_BINARY, Functions::getStartFile()], $command->getUsageExample()) . "\n";
        }

        echo "<color;fg=green>Options:</>\n";
        echo "  --help\n";
        echo "  --no-ansi\n";
    }
}
