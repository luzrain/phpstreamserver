<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

interface ConnectionInterface
{
    public const READ_BUFFER_SIZE = 204800;
    public const WRITE_BUFFER_SIZE = 204800;
    public const CONNECT_FAIL = 1;
    public const SEND_FAIL = 2;

    public function send(mixed $response): bool;
    public function getRemoteAddress(): string;
    public function getRemoteIp(): string;
    public function getRemotePort(): int;
    public function getLocalAddress(): string;
    public function getLocalIp(): string;
    public function getLocalPort(): int;
    public function close(): void;
    public function getStatistics(): ConnectionStatistics;
}
