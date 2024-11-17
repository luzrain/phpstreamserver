<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\System\Status;

use function Luzrain\PHPStreamServer\Internal\getDriverName;
use function Luzrain\PHPStreamServer\Internal\getStartFile;

final readonly class ServerStatus
{
    public string $eventLoop;
    public string $startFile;
    public \DateTimeImmutable $startedAt;

    public function __construct()
    {
        $this->eventLoop = getDriverName();
        $this->startFile = getStartFile();
        $this->startedAt = new \DateTimeImmutable('now');
    }
}
