<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Status;

use Luzrain\PHPStreamServer\Internal\Functions;

final readonly class ServerStatus
{
    public string $eventLoop;
    public string $startFile;
    public \DateTimeImmutable $startedAt;

    public function __construct()
    {
        $this->eventLoop = Functions::getDriverName();
        $this->startFile = Functions::getStartFile();
        $this->startedAt = new \DateTimeImmutable('now');
    }
}
