<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Console\Command\ReloadCommand;
use Luzrain\PhpRunner\Console\Command\StartCommand;
use Luzrain\PhpRunner\Console\Command\StatusCommand;
use Luzrain\PhpRunner\Console\Command\StopCommand;
use Luzrain\PhpRunner\Console\Console;
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
        $this->logger ??= new Logger(stdOut: true, logFile: $this->config->logFile);
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
        (new Console(
            new StartCommand($this->pool, $this->config, $this->logger),
            new StopCommand(),
            new ReloadCommand(),
            new StatusCommand($this->pool),
        ))->run();
    }
}
