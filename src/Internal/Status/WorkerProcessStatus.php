<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Status;

use Luzrain\PHPStreamServer\Internal\JsonSerializible;
use Luzrain\PHPStreamServer\Server\Connection\ActiveConnection;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionStatistics;

/**
 * @internal
 */
final readonly class WorkerProcessStatus implements \JsonSerializable
{
    use JsonSerializible;

    public function __construct(
        public int $pid,
        public string $user,
        public int $memory,
        public string $name,
        public \DateTimeImmutable $startedAt,
        public string|null $listen,
        public ConnectionStatistics|null $connectionStatistics,
        /** @var null|list<ActiveConnection> */
        public array|null $connections,
    ) {
    }
}
