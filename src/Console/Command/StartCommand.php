<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Config;
use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\MasterProcess;
use Luzrain\PhpRunner\WorkerPool;
use Psr\Log\LoggerInterface;

final class StartCommand implements Command
{
    public function __construct(
        private WorkerPool $pool,
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    public function getCommand(): string
    {
        return 'start';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% start [-d|--daemon]';
    }

    public function run(array $arguments): never
    {
        $process = new MasterProcess($this->pool, $this->config, $this->logger);
        $process->run(\in_array('-d', $arguments) || \in_array('--daemon', $arguments));
    }
}
