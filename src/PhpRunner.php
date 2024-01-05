<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Command\ConnectionsCommand;
use Luzrain\PhpRunner\Command\StatusJsonCommand;
use Luzrain\PhpRunner\Command\ProcessesCommand;
use Luzrain\PhpRunner\Command\ReloadCommand;
use Luzrain\PhpRunner\Command\StartCommand;
use Luzrain\PhpRunner\Command\StatusCommand;
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
        Config $config = new Config(),
        LoggerInterface|null $logger = null,
    ) {
        $logger ??= new Logger($config->logFile);
        $this->pool = new WorkerPool();
        $this->masterProcess = new MasterProcess($this->pool, $config, $logger);
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
