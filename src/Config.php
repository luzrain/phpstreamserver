<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

final readonly class Config
{
    public function __construct(
        /**
         * @var string|null file to write logs (only works with default logger)
         */
        public string|null $logFile = null,

        /**
         * @var string|null
         */
        public string|null $pidFile = null,

        /**
         * After sending the stop command, if the process is still alive, it will be forced to kill.
         *
         * @var int
         */
        public int $stopTimeout = 3,
    ) {
    }
}
