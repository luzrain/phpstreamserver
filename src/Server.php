<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\Command\ReloadCommand;
use Luzrain\PHPStreamServer\Command\StartCommand;
use Luzrain\PHPStreamServer\Command\StatusCommand;
use Luzrain\PHPStreamServer\Command\StopCommand;
use Luzrain\PHPStreamServer\Command\WorkersCommand;
use Luzrain\PHPStreamServer\Console\App;
use Luzrain\PHPStreamServer\Internal\Logger\Logger;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Module;

final class Server
{
    public const VERSION = '0.2.2';
    public const VERSION_STRING = 'phpstreamserver/' . self::VERSION;
    public const NAME = 'PHPStreamServer';
    public const TITLE = '🌸 PHPStreamServer - PHP application server';

    private MasterProcess $masterProcess;

    public function __construct(
        /**
         * Defines a file that will store the process ID of the main process.
         */
        string|null $pidFile = null,

        /**
         * Timeout in seconds that master process will be waiting before force kill child processes after sending stop command.
         */
        public int $stopTimeout = 6,
    ) {
        $this->masterProcess = new MasterProcess(
            pidFile: $pidFile,
            stopTimeout: $this->stopTimeout,
            logger: new Logger(null),
        );
    }

    public function addWorkersProcess(WorkerProcess ...$workers): self
    {
        $this->masterProcess->addWorkerProcess(...$workers);

        return $this;
    }

    public function addPeriodicProcess(PeriodicProcess ...$workers): self
    {
        $this->masterProcess->addPeriodicProcess(...$workers);

        return $this;
    }

    public function addModules(Module ...$module): self
    {
        $this->masterProcess->addModules(...$module);

        return $this;
    }

    public function run(string $cmd = ''): int
    {
        return (new App(
            new StartCommand($this->masterProcess),
            new StopCommand($this->masterProcess),
            new ReloadCommand($this->masterProcess),
            new StatusCommand($this->masterProcess),
            new WorkersCommand($this->masterProcess),
            new ProcessesCommand($this->masterProcess),
            new ConnectionsCommand($this->masterProcess),
        ))->run($cmd);
    }
}
