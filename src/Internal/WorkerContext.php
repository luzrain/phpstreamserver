<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

/**
 * @internal
 */
final readonly class WorkerContext
{
    public function __construct(
        public string $socketFile,
    ) {
    }
}
