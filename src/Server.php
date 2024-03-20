<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Command\ConnectionsCommand;
use Luzrain\PHPStreamServer\Command\ProcessesCommand;
use Luzrain\PHPStreamServer\Command\ReloadCommand;
use Luzrain\PHPStreamServer\Command\StartCommand;
use Luzrain\PHPStreamServer\Command\StatusCommand;
use Luzrain\PHPStreamServer\Command\StatusJsonCommand;
use Luzrain\PHPStreamServer\Command\StopCommand;
use Luzrain\PHPStreamServer\Command\WorkersCommand;
use Luzrain\PHPStreamServer\Console\App;
use Luzrain\PHPStreamServer\Internal\Logger;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Internal\Status\MasterProcessStatus;
use Luzrain\PHPStreamServer\Internal\WorkerPool;
use Psr\Log\LoggerInterface;

final class Server
{
    public const VERSION = '0.1.0';
    public const VERSION_STRING = 'phprunner/' . self::VERSION;
    public const NAME = 'PhpRunner';
    public const TITLE = 'PhpRunner - PHP application server';

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

    public function stop(): void
    {
        $this->masterProcess->stop();
    }

    public function reload(): void
    {
        $this->masterProcess->reload();
    }

    public function getStatus(): MasterProcessStatus
    {
        return $this->masterProcess->getStatus();
    }
}
