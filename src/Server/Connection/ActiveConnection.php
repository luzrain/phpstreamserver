<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Internal\JsonSerializible;

final class ActiveConnection implements \JsonSerializable
{
    use JsonSerializible;

    private static \WeakMap $map;

    /**
     * @return list<self>
     */
    public static function getList(): array
    {
        return isset(self::$map) ? \iterator_to_array(self::$map, false) : [];
    }

    public static function addConnection(ConnectionInterface $connection): void
    {
        self::$map ??= new \WeakMap();
        self::$map[$connection] = new self(
            localIp: $connection->getLocalIp(),
            localPort: (string) $connection->getLocalPort(),
            remoteIp: $connection->getRemoteIp(),
            remotePort: (string) $connection->getRemotePort(),
            connectedAt: new \DateTimeImmutable(),
            statistics: $connection->getStatistics(),
        );
    }

    private function __construct(
        public string $localIp,
        public string $localPort,
        public string $remoteIp,
        public string $remotePort,
        public \DateTimeImmutable $connectedAt,
        public ConnectionStatistics $statistics,
    ) {
    }
}
