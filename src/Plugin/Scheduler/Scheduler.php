<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Scheduler;

use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\PeriodicProcess;
use Luzrain\PHPStreamServer\Plugin\PcntlExecCommand;
use Luzrain\PHPStreamServer\Plugin\NullStop;
use Luzrain\PHPStreamServer\Plugin\PluginInterface;

final readonly class Scheduler implements PluginInterface
{
    use NullStop;
    use PcntlExecCommand;

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

        $pcntlExec = \is_string($this->command) ? $this->prepareCommandForPcntlExec($this->command) : null;

        $masterProcess->addWorker(new PeriodicProcess(
            name: $name,
            schedule: $this->schedule,
            jitter: $this->jitter,
            user: $this->user,
            group: $this->group,
            onStart: function (PeriodicProcess $worker) use ($pcntlExec) {
                if ($pcntlExec !== null) {
                    $worker->exec(...$pcntlExec);
                } else {
                    ($this->command)($worker);
                }
            },
        ));
    }
}
