<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Status;

use Luzrain\PhpRunner\Internal\JsonSerializible;

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
    ) {
    }
}
