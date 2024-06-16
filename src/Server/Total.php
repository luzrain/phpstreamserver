<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server;

use Luzrain\PHPStreamServer\Internal\JsonSerializible;

final class Total implements \JsonSerializable
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

    /**
     * @readonly
     * @psalm-allow-private-mutation
     */
    public int $connections = 0;

    /**
     * @readonly
     * @psalm-allow-private-mutation
     */
    public int $requests = 0;

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

    /**
     * @param positive-int $val
     */
    public function incConnections(int $val): void
    {
        $this->connections += $val;
    }

    /**
     * @param positive-int $val
     */
    public function incRequests(int $val): void
    {
        $this->requests += $val;
    }
}
