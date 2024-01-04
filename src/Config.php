<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

final readonly class Config
{
    public function __construct(
        /**
         * Defines a file that will store the process ID of the main process.
         */
        public string|null $pidFile = null,

        /**
         * Defines a file that will store logs. Only works with default logger.
         */
        public string|null $logFile = null,

        /**
         * Timeout in seconds that master process will be waiting before force kill child processes after sending stop command.
         */
        public int $stopTimeout = 3,
    ) {
    }
}
