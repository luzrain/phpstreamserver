<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

interface LoggerInterface extends \Psr\Log\LoggerInterface
{
    public function withChannel(string $channel): self;
}
