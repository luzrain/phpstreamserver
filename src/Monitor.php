<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

final class Monitor
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function getStatus(): int
    {
        $masterPid = \is_file($this->pidFile) ? (int)\file_get_contents($this->pidFile) : 0;
        $isRunning = !empty($masterPid) && \posix_getpid() !== $masterPid && \posix_kill($masterPid, 0);

        return $isRunning ? self::STATUS_RUNNING : $this->status;
    }

    public function getStartedAt(): \DateTimeImmutable
    {

    }
}
