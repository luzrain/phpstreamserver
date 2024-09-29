<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Supervisor;

use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\PcntlExecCommand;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\WorkerProcess;

final class Supervisor extends Plugin
{
    use PcntlExecCommand;

    private array|null $pcntlExec;

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

    public function init(MasterProcess $masterProcess): void
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
                if ($this->pcntlExec !== null) {
                    $worker->exec(...$this->pcntlExec);
                } else {
                    ($this->command)($worker);
                }
            },
        ));
    }

    public function start(): void
    {
        $this->pcntlExec = \is_string($this->command) ? $this->prepareCommandForPcntlExec($this->command) : null;
    }
}
