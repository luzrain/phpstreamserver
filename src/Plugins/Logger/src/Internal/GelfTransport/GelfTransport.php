<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\GelfTransport;

interface GelfTransport
{
    public function start(): void;

    public function write(string $buffer): void;
}
