<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\System\Status;

use Luzrain\PHPStreamServer\Internal\Functions;
use Revolt\EventLoop\DriverFactory;

final readonly class ServerStatus
{
    public string $eventLoop;
    public string $startFile;
    public \DateTimeImmutable $startedAt;

    public function __construct()
    {
        $this->eventLoop = (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
        $this->startFile = Functions::getStartFile();
        $this->startedAt = new \DateTimeImmutable('now');
    }
}
