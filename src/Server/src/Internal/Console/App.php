<?php

declare(strict_types=1);

namespace PHPStreamServer\Internal\Console;

use PHPStreamServer\Console\Command;
use PHPStreamServer\Console\Options;
use PHPStreamServer\Console\Table;
use PHPStreamServer\Server;
use function PHPStreamServer\Internal\getStartFile;

/**
 * @internal
 */
final class App
{
    /**
     * @var array<class-string<Command>, Command>
     */
    private array $commands = [];

    private string|null $command = null;
    private array $parsedOptions = [];

    private Options $options;

    public function __construct(Command ...$commands)
    {
        $this->options = new Options(
            parsedOptions: $this->parsedOptions,
            defaultOptionDefinitions: [
                new OptionDefinition('help', 'h', 'Show help'),
                new OptionDefinition('quiet', 'q', 'Do not output any message'),
                new OptionDefinition('no-color', null, 'Disable color output'),
            ]
        );

        foreach ($commands as $command) {
            if (!isset($this->commands[$command::class])) {
                $this->commands[$command::class] = $command;
            }
        }
    }

    private function getArgvs(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        unset($argv[0]);
        return \array_values($argv);
    }

    private function parseArgvs(array $arguments): array
    {
        $options = [];
        for ($i = 0; $i < \count($arguments); $i++) {
            if (\str_starts_with($arguments[$i], '--')) {
                $optionParts = \explode('=', \substr($arguments[$i], 2), 2);
                $options[$optionParts[0]] = $optionParts[1] ?? true;
            } elseif (\str_starts_with($arguments[$i], '-')) {
                $splitOtions = \str_split(\substr($arguments[$i], 1));
                foreach ($splitOtions as $option) {
                    $options[$option] = true;
                    if (isset($arguments[$i + 1]) && !\str_starts_with($arguments[$i + 1], '-') && \count($splitOtions) === 1) {
                        $options[$option] = $arguments[++$i];
                    }
                }
            }
        }
        return $options;
    }

    public function run(\WeakMap $args): int
    {
        $argv = $this->getArgvs();
        $this->parsedOptions = $this->parseArgvs($argv);
        $cmdCommand = $argv[0] ?? null;
        if ($cmdCommand !== null && !\str_starts_with($cmdCommand, '-')) {
            $this->command = $cmdCommand;
        }

        if ($this->options->hasOption('no-color')) {
            Colorizer::disableColor();
        }

        StdoutHandler::register();
        if ($this->options->hasOption('quiet')) {
            StdoutHandler::disableStdout();
        }

        foreach ($this->commands as $command) {
            if ($command::COMMAND === $this->command) {
                $command->options = $this->options;
                $command->configure();

                if ($this->options->hasOption('help')) {
                    $this->showHelpForCommand($command);
                    return 0;
                }

                try {
                    return $command->execute($args->getIterator()->current());
                } catch (ServerIsNotRunning) {
                    echo \sprintf("<color;bg=red>%s is not running</>\n", Server::NAME);
                    return 1;
                } catch (ServerIsRunning) {
                    echo \sprintf("<color;bg=red>%s already running</>\n", Server::NAME);
                    return 1;
                }
            }
        }

        $this->options->addOptionDefinition('version', null, 'Show version');

        if ($this->command !== null) {
            echo \sprintf("<color;bg=red>✘ Command \"%s\" does not exist</>\n", $this->command);
            return 1;
        }

        if ($this->options->hasOption('version')) {
            echo \sprintf("%s\n", Server::getVersion());
            return 0;
        }

        $this->showHelp();
        return 0;
    }

    private function showHelp(): void
    {
        echo \sprintf("%s (%s)\n", Server::TITLE, Server::getVersion());
        echo "<color;fg=yellow>Usage:</>\n";
        echo \sprintf("  %s <command> [options]\n", \basename(getStartFile()));
        echo "<color;fg=yellow>Commands:</>\n";
        echo (new Table(indent: 1))->addRows(\array_map(
            array: $this->commands,
            callback: static function (Command $command) {
                return [\sprintf('<color;fg=green>%s</>', $command::COMMAND), $command::DESCRIPTION];
            },
        ));
        echo "<color;fg=yellow>Options:</>\n";
        echo (new Table(indent: 1))->addRows($this->createOptionsTableRows());
    }

    private function showHelpForCommand(Command $command): void
    {
        echo \sprintf("%s (%s)\n", Server::TITLE, Server::getVersion());
        echo "<color;fg=yellow>Description:</>\n";
        echo \sprintf("  %s\n", $command::DESCRIPTION);
        echo "<color;fg=yellow>Usage:</>\n";
        echo \sprintf("  %s %s [options]\n", \basename(getStartFile()), $command::COMMAND);
        echo "<color;fg=yellow>Options:</>\n";
        echo (new Table(indent: 1))->addRows($this->createOptionsTableRows());
    }

    private function createOptionsTableRows(): array
    {
        $definitions = $this->options->getOptionDefinitions();

        $options = [];
        foreach ($definitions as $option) {
            $options[] = [
                \sprintf('<color;fg=green>%s--%s</>', $option->shortName !== null ? '-' . $option->shortName . ', ' : '    ', $option->name),
                $option->description,
            ];
        }
        return $options;
    }
}
