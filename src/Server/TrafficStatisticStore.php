<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server;

use Amp\Socket\Socket;

final class TrafficStatisticStore
{
    /**
     * @var \WeakMap<Socket, Connection>
     */
    private \WeakMap $map;

    private Total $total;

    public function __construct()
    {
        $this->map = new \WeakMap();
        $this->total = new Total();
    }

    /**
     * @return list<Connection>
     */
    public function getConnections(): array
    {
        return \iterator_to_array($this->map, false);
    }

    public function getTotal(): Total
    {
        return $this->total;
    }

    public function addConnection(Socket $key, Connection $connection): void
    {
        $this->map[$key] = $connection;
        $this->total->incConnections(1);
    }

    public function removeConnection(Socket $key): void
    {
        unset($this->map[$key]);
    }

    /**
     * @param positive-int $val
     */
    public function incRx(Socket $key, int $val): void
    {
        $this->map[$key]->incRx($val);
        $this->total->incRx($val);
    }

    /**
     * @param positive-int $val
     */
    public function incTx(Socket $key, int $val): void
    {
        $this->map[$key]->incTx($val);
        $this->total->incTx($val);
    }

    public function incRequests(int $val = 1): void
    {
        $this->total->incRequests($val);
    }
}
