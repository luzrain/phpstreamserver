<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Scheduler;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Luzrain\PHPStreamServer\Plugin\PluginInterface;
use Luzrain\PHPStreamServer\Server;
use function Amp\async;

final readonly class Scheduler implements PluginInterface
{
    /**
     * @param string|\Closure(PeriodicProcess): void $command bash command as string or php closure
     */
    public function __construct(
        private string $schedule,
        private string|\Closure $command,
        private string|null $name = null,
        private int $jitter = 0,
        private string|null $user = null,
        private string|null $group = null,
    ) {
    }

    public function start(MasterProcess $masterProcess): void
    {
        $name = match (true) {
            $this->name === null && \is_string($this->command) => $this->command,
            $this->name === null => 'closure',
            default => $this->name,
        };

        if (\is_string($this->command) && $this->isCommandContainsLogicOperators($this->command)) {
            throw new \RuntimeException(\sprintf(
                '%s does not directly support executing multiple commands with logical operators. Use shell with -c option e.g. "/bin/sh -c "%s"',
                Server::NAME,
                $this->command
            ));
        }

        $masterProcess->addWorker(new PeriodicProcess(
            name: $name,
            schedule: $this->schedule,
            jitter: $this->jitter,
            user: $this->user,
            group: $this->group,
            onStart: function (PeriodicProcess $worker) {
                if (\is_string($this->command)) {
                    $worker->exec(...$this->prepareCommand($this->command));
                } else {
                    ($this->command)($worker);
                }
            },
        ));
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function prepareCommand(string $command): array
    {
        \preg_match_all('/\'[^\']*\'|"[^"]*"|\S+/', $command, $matches);
        $parts = \array_map(static fn (string $part) => \trim($part, '"\''), $matches[0]);
        $binary = \array_shift($parts);
        $args = $parts;

        if (!\str_starts_with($binary, '/')) {
            if (\is_string($absoluteBinaryPath = \shell_exec("command -v $binary"))) {
                $binary = \trim($absoluteBinaryPath);
            }
        }

        return [$binary, $args];
    }

    private function isCommandContainsLogicOperators(string $command): bool
    {
        return \preg_match('/(\'[^\']*\'|"[^"]*")(*SKIP)(*FAIL)|&&|\|\|/', $command) === 1;
    }

    public function stop(): Future
    {
        return async(static fn() => null);
    }
}
