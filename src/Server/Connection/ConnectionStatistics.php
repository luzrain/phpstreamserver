<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Internal\JsonSerializible;

/**
 * @internal
 */
final class ConnectionStatistics implements \JsonSerializable
{
    use JsonSerializible;

    private static self $globalInstance;

    private int $rx = 0;
    private int $tx = 0;
    private int $packages = 0;
    private int $fails = 0;

    public static function getGlobal(): self
    {
        return self::$globalInstance ??= new self();
    }

    public function incRx(int $val): void
    {
        $this->rx += $val;
        self::getGlobal()->rx += $val;
    }

    public function incTx(int $val): void
    {
        $this->tx += $val;
        self::getGlobal()->tx += $val;
    }

    public function incPackages(int $val = 1): void
    {
        $this->packages += $val;
        self::getGlobal()->packages += $val;
    }

    public function incFails(int $val = 1): void
    {
        $this->fails += $val;
        self::getGlobal()->fails += $val;
    }

    public function getRx(): int
    {
        return $this->rx;
    }

    public function getTx(): int
    {
        return $this->tx;
    }

    public function getPackages(): int
    {
        return $this->packages;
    }

    public function getFails(): int
    {
        return $this->fails;
    }
}
