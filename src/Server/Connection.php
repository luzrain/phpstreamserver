<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server;

final class Connection
{
    public readonly \DateTimeImmutable $connectedAt;
    public readonly string $localIp;
    public readonly string $localPort;
    public readonly string $remoteIp;
    public readonly string $remotePort;
    public int $rx = 0;
    public int $tx = 0;

    public function __construct(string $localIp, string $localPort, string $remoteIp, string $remotePort)
    {
        $this->connectedAt = new \DateTimeImmutable('now');
        $this->localIp = $localIp;
        $this->localPort = $localPort;
        $this->remoteIp = $remoteIp;
        $this->remotePort = $remotePort;
    }
}
