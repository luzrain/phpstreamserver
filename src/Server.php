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
use Luzrain\PHPStreamServer\Internal\ServerStatus\Connection;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Internal\WorkerPool;
use Psr\Log\LoggerInterface;

final class Server
{
    public const VERSION = '0.2.2';
    public const VERSION_STRING = 'phpstreamserver/' . self::VERSION;
    public const NAME = 'PHPStreamServer';
    public const TITLE = '🌸 PHPStreamServer - PHP application server';

    /**
     * @var \WeakReference<WorkerPool>
     */
    private \WeakReference $workerPool;
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
        $this->workerPool = \WeakReference::create($workerPool = new WorkerPool());
        $this->masterProcess = new MasterProcess(
            pidFile: $pidFile,
            stopTimeout: $this->stopTimeout,
            workerPool: $workerPool,
            logger: $logger ?? new Logger($logFile),
        );
    }

    public function addWorkers(WorkerProcess ...$workers): self
    {
        \array_walk($workers, $this->workerPool->get()->addWorker(...));

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

    public function getServerStatus(): ServerStatus
    {
        return $this->masterProcess->getServerStatus();
    }

    /**
     * @return list<Connection>
     */
    public function getServerConnections(): array
    {
        return $this->masterProcess->getServerConnections();
    }
}
