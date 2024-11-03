<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message;

use Luzrain\PHPStreamServer\MessageBus\Message;

/**
 * Process spawned
 * @implements Message<null>
 */
final readonly class ProcessSpawnedEvent implements Message
{
    public function __construct(
        public int $workerId,
        public int $pid,
        public string $user,
        public string $name,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
