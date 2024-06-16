<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server;

use Luzrain\PHPStreamServer\Internal\JsonSerializible;

final class Connection implements \JsonSerializable
{
    use JsonSerializible;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     */
    public int $rx = 0;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     */
    public int $tx = 0;

    public \DateTimeImmutable $connectedAt;

    public function __construct(
        public string $localIp,
        public string $localPort,
        public string $remoteIp,
        public string $remotePort,
    ) {
        $this->connectedAt = new \DateTimeImmutable();
    }

    /**
     * @param positive-int $val
     */
    public function incRx(int $val): void
    {
        $this->rx += $val;
    }

    /**
     * @param positive-int $val
     */
    public function incTx(int $val): void
    {
        $this->tx += $val;
    }
}
