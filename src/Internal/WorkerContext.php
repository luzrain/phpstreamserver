<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final readonly class WorkerContext
{
    public function __construct(
        public string $socketFile,
        public LoggerInterface $logger,
    ) {
    }
}
