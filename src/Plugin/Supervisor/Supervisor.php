<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Supervisor;

use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\PcntlExecCommand;
use Luzrain\PHPStreamServer\Plugin\NullStop;
use Luzrain\PHPStreamServer\Plugin\PluginInterface;
use Luzrain\PHPStreamServer\WorkerProcess;

final readonly class Supervisor implements PluginInterface
{
    use NullStop;
    use PcntlExecCommand;

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

        $pcntlExec = \is_string($this->command) ? $this->prepareCommandForPcntlExec($this->command) : null;

        $masterProcess->addWorker(new WorkerProcess(
            name: $name,
            count: $this->count,
            reloadable: $this->reloadable,
            restartDelay: $this->restartDelay,
            user: $this->user,
            group: $this->group,
            onStart: function (WorkerProcess $worker) use ($pcntlExec) {
                if ($pcntlExec !== null) {
                    $worker->exec(...$pcntlExec);
                } else {
                    ($this->command)($worker);
                }
            },
        ));
    }
}
