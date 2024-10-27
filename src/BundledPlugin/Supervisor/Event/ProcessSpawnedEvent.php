<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * Process spawned
 * @implements Message<void>
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
