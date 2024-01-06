<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Command\ConnectionsCommand;
use Luzrain\PhpRunner\Command\ProcessesCommand;
use Luzrain\PhpRunner\Command\ReloadCommand;
use Luzrain\PhpRunner\Command\StartCommand;
use Luzrain\PhpRunner\Command\StatusCommand;
use Luzrain\PhpRunner\Command\StatusJsonCommand;
use Luzrain\PhpRunner\Command\StopCommand;
use Luzrain\PhpRunner\Command\WorkersCommand;
use Luzrain\PhpRunner\Console\App;
use Luzrain\PhpRunner\Internal\Logger;
use Luzrain\PhpRunner\Internal\MasterProcess;
use Luzrain\PhpRunner\Internal\WorkerPool;
use Psr\Log\LoggerInterface;

final class PhpRunner
{
    public const VERSION = '0.0.1';
    public const VERSION_STRING = 'phprunner/' . self::VERSION;

    private WorkerPool $pool;
    private MasterProcess $masterProcess;

    public function __construct(
        /**
         * Defines a file that will store the process ID of the main process.
         */
        string|null $pidFile = null,

        /**
         * Defines a file that will store logs. Only works with default logger.
         */
        string|null $logFile = null,

        /**
         * Timeout in seconds that master process will be waiting before force kill child processes after sending stop command.
         */
        public int $stopTimeout = 3,

        /**
         * PSR-3 compatible logger
         */
        LoggerInterface|null $logger = null,
    ) {
        $this->pool = new WorkerPool();
        $this->masterProcess = new MasterProcess(
            pidFile: $pidFile,
            stopTimeout: $this->stopTimeout,
            pool: $this->pool,
            logger: $logger ?? new Logger($logFile),
        );
    }

    public function addWorkers(WorkerProcess ...$workers): self
    {
        \array_walk($workers, $this->pool->addWorker(...));

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
            new StatusJsonCommand($this->masterProcess),
        ))->run($cmd);
    }
}
