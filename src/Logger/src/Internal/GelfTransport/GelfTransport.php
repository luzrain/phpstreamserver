<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger\Internal\GelfTransport;

interface GelfTransport
{
    public function start(): void;

    public function write(string $buffer): void;
}
