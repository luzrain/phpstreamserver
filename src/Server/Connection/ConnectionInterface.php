<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Connection;

use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterInterface;

interface ConnectionInterface extends EventEmitterInterface
{
    public const EVENT_CONNECT = 'connect';
    public const EVENT_CLOSE = 'close';
    public const EVENT_DATA = 'data';
    public const EVENT_ERROR = 'error';

    public const READ_CHUNK_SIZE = 65536;
    public const WRITE_CHUNK_SIZE = 204800;

    public function send(mixed $response): bool;
    public function getRemoteAddress(): string;
    public function getRemoteIp(): string;
    public function getRemotePort(): int;
    public function getLocalAddress(): string;
    public function getLocalIp(): string;
    public function getLocalPort(): int;
    public function close(): void;
    public function getStatistics(): ConnectionStatistics;
    public function accept(): void;
}
