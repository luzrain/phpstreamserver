<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;

/**
 * @internal
 */
final class WorkerContext
{
    public function __construct(
        public readonly string $socketFile,

        /**
         * @var \Closure(): LoggerInterface
         */
        public \Closure $loggerFactory,
    ) {
    }
}
