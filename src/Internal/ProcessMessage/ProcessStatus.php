<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ProcessMessage;

use Luzrain\PHPStreamServer\Server\Connection;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionStatistics;

/**
 * @internal
 */
final readonly class ProcessStatus implements Message
{
    public function __construct(
        private int $pid,
        public int $memory,
        public string $listen,
        public ConnectionStatistics $connectionStatistics,
        /** @var list<Connection> */
        public array $connections,
    ) {
    }

    public function getPid(): int
    {
        return $this->pid;
    }
}
