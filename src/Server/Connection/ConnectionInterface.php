<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Connection;

interface ConnectionInterface
{
    public const CONNECT_FAIL = 1;
    public const SEND_FAIL = 2;

    public function send(string|\Stringable $sendBuffer): bool;
    public function getRemoteAddress(): string;
    public function getRemoteIp(): string;
    public function getRemotePort(): int;
    public function getLocalAddress(): string;
    public function getLocalIp(): string;
    public function getLocalPort(): int;
    public function close(): void;
}
