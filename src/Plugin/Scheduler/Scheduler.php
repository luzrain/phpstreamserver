<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Scheduler;

use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Luzrain\PHPStreamServer\Plugin\PcntlExecCommand;
use Luzrain\PHPStreamServer\Plugin\Plugin;

final class Scheduler extends Plugin
{
    use PcntlExecCommand;

    private array|null $pcntlExec;

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

    public function init(MasterProcess $masterProcess): void
    {
        $name = match (true) {
            $this->name === null && \is_string($this->command) => $this->command,
            $this->name === null => 'closure',
            default => $this->name,
        };

        $masterProcess->addWorker(new PeriodicProcess(
            name: $name,
            schedule: $this->schedule,
            jitter: $this->jitter,
            user: $this->user,
            group: $this->group,
            onStart: function (PeriodicProcess $worker) {
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
