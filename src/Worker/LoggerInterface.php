<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Worker;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

interface LoggerInterface extends PsrLoggerInterface
{
    public function withChannel(string $channel): self;
}
