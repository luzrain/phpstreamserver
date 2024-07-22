<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class Spawn implements Message
{
    public function __construct(
        public int $pid,
        public string $user,
        public string $name,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
