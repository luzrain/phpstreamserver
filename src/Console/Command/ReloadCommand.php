<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console\Command;

use Luzrain\PhpRunner\Config;
use Luzrain\PhpRunner\Console\Command;
use Luzrain\PhpRunner\MasterProcess;
use Luzrain\PhpRunner\WorkerPool;
use Psr\Log\LoggerInterface;

final class ReloadCommand implements Command
{
    public function __construct()
    {
    }

    public function getCommand(): string
    {
        return 'reload';
    }

    public function getUsageExample(): string
    {
        return '%php_bin% %start_file% reload';
    }

    public function run(array $arguments): never
    {
        exit;
    }
}
