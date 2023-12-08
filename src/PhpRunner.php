<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Command\ConnectionsCommand;
use Luzrain\PhpRunner\Command\ProcessesCommand;
use Luzrain\PhpRunner\Command\ReloadCommand;
use Luzrain\PhpRunner\Command\StartCommand;
use Luzrain\PhpRunner\Command\StatusCommand;
use Luzrain\PhpRunner\Command\StopCommand;
use Luzrain\PhpRunner\Command\WorkersCommand;
use Luzrain\PhpRunner\Console\App;
use Luzrain\PhpRunner\Internal\Logger;
use Psr\Log\LoggerInterface;

final class PhpRunner
{
    public const VERSION = '0.0.1';

    private WorkerPool $pool;

    public function __construct(
        private Config|null $config = null,
        private LoggerInterface|null $logger = null,
    ) {
        $this->config ??= new Config();
        $this->logger ??= new Logger($this->config->logFile);
        $this->pool = new WorkerPool();
    }

    public function addWorker(WorkerProcess ...$workers): self
    {
        foreach ($workers as $worker) {
            $this->pool->addWorker($worker);
        }

        return $this;
    }

    public function run(): never
    {
        $masterProcess = new MasterProcess($this->pool, $this->config, $this->logger);

        (new App(
            new StartCommand($masterProcess),
            new StopCommand($masterProcess),
            new ReloadCommand($masterProcess),
            new StatusCommand($masterProcess),
            new WorkersCommand($masterProcess),
            new ProcessesCommand($masterProcess),
            new ConnectionsCommand(),
        ))->run();
    }
}
