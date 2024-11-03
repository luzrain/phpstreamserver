<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

use Luzrain\PHPStreamServer\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class NullLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct()
    {
    }

    public function withChannel(string $channel): self
    {
        return $this;
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        // do nothing
    }
}
