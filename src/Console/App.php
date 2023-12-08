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

    public function run(string $cmd = ''): never
    {
        [$option, $arguments] = $this->parseCommand($cmd);

        if (\in_array('-h', $arguments) || \in_array('--help', $arguments)) {
            $this->showHelp();
            exit(0);
        }

        foreach ($this->commands as $command) {
            if ($command->getOption() === $option) {
                $command->run($arguments);
                exit(0);
            }
        }

        $this->showHelp();
        exit(0);
    }

    /**
     * @param string $cmd
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
        echo "<color;fg=green>Usage:</>\n";
        foreach ($this->commands as $command) {
            echo '  ' . str_replace(['%php_bin%', '%start_file%'], [PHP_BINARY, Functions::getStartFile()], $command->getUsageExample()) . "\n";
        }

        echo "<color;fg=green>Options:</>\n";
        echo "  --help\n";
        echo "  --no-ansi\n";
    }
}
