<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console;

use Luzrain\PhpRunner\Internal\Functions;

final class App
{
    private array $commands;

    public function __construct(Command ...$commands)
    {
        $this->commands = $commands;
    }

    public function run(string $cmd = ''): int
    {
        [$option, $arguments] = $this->parseCommand($cmd);

        // Supress any output
        if (\in_array('-q', $arguments) || \in_array('--quiet', $arguments)) {
            \ob_start(static fn() => '', 1);
        }

        // Force show help
        if (\in_array('-h', $arguments) || \in_array('--help', $arguments)) {
            $this->showHelp();
            return 0;
        }

        foreach ($this->commands as $command) {
            if ($command->getCommand() === $option) {
                $command->run($arguments);
                return 0;
            }
        }

        // Show help by default
        $this->showHelp();
        return 0;
    }

    /**
     * @return array{string, array}
     */
    private function parseCommand(string $cmd): array
    {
        if ($cmd === '') {
            $argv = $_SERVER['argv'];
            unset($argv[0]);
            $cmd = \implode(' ', $argv);
        }

        $parts = \explode(' ', $cmd, 2);

        return [$parts[0] ?? '', \array_filter(\explode(' ', $parts[1] ?? ''))];
    }

    private function showHelp(): void
    {
        echo "PHPRunner - PHP application server\n";
        echo "<color;fg=yellow>Usage:</>\n";
        echo \sprintf("  %s %s <command> [options]\n", PHP_BINARY, Functions::getStartFile());
        echo "<color;fg=yellow>Options:</>\n";
        echo (new Table(indent: 1))->addRows([
            ['<color;fg=green>-h, --help</>', 'Show help'],
            ['<color;fg=green>-q, --quiet</>', 'Do not output any message'],
            ['<color;fg=green>-d, --daemon</>', 'Run in daemon mode'],
        ]);
        echo "<color;fg=yellow>Commands:</>\n";
        echo (new Table(indent: 1))->addRows(\array_map(array: $this->commands, callback: function (Command $command) {
            return ["<color;fg=green>{$command->getCommand()}</>", $command->getHelp()];
        }));
    }
}
