<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

final readonly class Connect implements Message
{
    public function __construct(
        public int $pid,
        public int $connectionId,
        public \DateTimeImmutable $connectedAt,
        public string $localIp,
        public string $localPort,
        public string $remoteIp,
        public string $remotePort,
        public int $rx = 0,
        public int $tx = 0,
    ) {
    }
}
