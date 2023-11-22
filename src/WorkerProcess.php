<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;

class WorkerProcess
{
    private LoggerInterface $logger;
    private Driver $eventLoop;

    private int $exitCode = 0;

    public function __construct(
        public readonly string $name = 'none',
        public readonly int $count = 1,
        public readonly bool $reloadable = true,
        public readonly int $ttl = 0,
        public readonly int $maxMemory = 0,
        public readonly string|null $user = null,
        public readonly string|null $group = null,
        private readonly \Closure|null $onStart = null,
        private readonly \Closure|null $onStop = null,
        private readonly \Closure|null $onReload = null,
    ) {
    }

    /**
     * @internal
     */
    final public function setDependencies(Driver $eventLoop, LoggerInterface $logger): void
    {
        $this->eventLoop = $eventLoop;
        $this->logger = $logger;
    }

    /**
     * @internal
     */
    final public function run(): int
    {
        $this->setUserAndGroup();
        $this->initWorker();
        $this->initSignalHandler();
        $this->eventLoop->run();
        return $this->exitCode;
    }

    private function initWorker(): void
    {
        // onStart callback
        if($this->onStart !== null) {
            $this->eventLoop->defer(function (): void {
                ($this->onStart)();
            });
        }

        // Watch ttl
        if ($this->ttl > 0) {
            $this->eventLoop->delay($this->ttl, function (): void {
                $this->stop();
            });
        }

        // Watch max memory
        if ($this->maxMemory > 0) {
            $this->eventLoop->repeat(15, function (): void {
                // TODO
            });
        }
    }

    private function initSignalHandler(): void
    {
        foreach ([SIGTERM] as $signo) {
            $this->eventLoop->onSignal($signo, function (string $id, int $signo): void {
                match ($signo) {
                    SIGTERM => $this->stop(),
                };
            });
        }
    }

    private function setUserAndGroup(): void
    {
        $currentUser = (\posix_getpwuid(\posix_getuid()) ?: [])['name'] ?? 'unknown';
        $user = $this->user ?? $currentUser;

        if (\posix_getuid() !== 0 && $user !== $currentUser) {
            $this->logger->warning('You must have the root privileges to change the user and group', ['worker' => $this->name]);
            return;
        }

        // Get uid
        if ($userInfo = \posix_getpwnam($user)) {
            $uid = $userInfo['uid'];
        } else {
            $this->logger->warning(sprintf('User "%s" does not exist', $user), ['worker' => $this->name]);
            return;
        }

        // Get gid
        if ($this->group === null) {
            $gid = $userInfo['gid'];
        } elseif ($groupInfo = \posix_getgrnam($this->group)) {
            $gid = $groupInfo['gid'];
        } else {
            $this->logger->warning(sprintf('Group "%s" does not exist', $this->group), ['worker' => $this->name]);
            return;
        }

        // Set uid and gid
        if ($uid !== \posix_getuid() || $gid !== \posix_getgid()) {
            if (!\posix_setgid($gid) || !\posix_initgroups($userInfo['name'], $gid) || !\posix_setuid($uid)) {
                $this->logger->warning('Changing guid or uid fails', ['worker' => $this->name]);
            }
        }
    }

    private function stop(int $code = 0): void
    {
        $this->exitCode = $code;
        try {
            if($this->onStop !== null) {
                ($this->onStop)();
            }
        } finally {
            $this->eventLoop->stop();
        }
    }
}
