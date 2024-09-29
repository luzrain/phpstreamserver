<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Message;

use Luzrain\PHPStreamServer\Message;

/**
 * Process spawned
 * @implements Message<void>
 */
final readonly class ProcessSpawnedEvent implements Message
{
    public function __construct(
        public int $pid,
        public string $user,
        public string $name,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
