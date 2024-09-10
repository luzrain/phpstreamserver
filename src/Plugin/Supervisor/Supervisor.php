<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Supervisor;

use Amp\Future;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\PluginInterface;
use Luzrain\PHPStreamServer\WorkerProcess;
use function Amp\async;

final readonly class Supervisor implements PluginInterface
{
    /**
     * @param string|\Closure(WorkerProcess): void $command bash command as string or php closure
     */
    public function __construct(
        private string|\Closure $command,
        private string|null $name = null,
        private int $count = 1,
        private float $restartDelay = 0.5,
        private bool $reloadable = true,
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

        $masterProcess->addWorker(new WorkerProcess(
            name: $name,
            count: $this->count,
            reloadable: $this->reloadable,
            restartDelay: $this->restartDelay,
            user: $this->user,
            group: $this->group,
            onStart: function (WorkerProcess $worker) {
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

    public function stop(): Future
    {
        return async(static fn() => null);
    }
}
