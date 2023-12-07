<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Internal\ErrorHandler;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Status\WorkerProcessStatus;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

/**
 * @internal
 */
class WorkerProcess
{
    private LoggerInterface $logger;
    private Driver $eventLoop;
    private \DateTimeImmutable $startedAt;

    /**
     * @var resource parent socket for interprocess communication
     */
    private mixed $parentSocket;

    private int $exitCode = 0;

    public function __construct(
        public readonly string $name = 'none',
        public readonly int $count = 1,
        public readonly bool $reloadable = true,
        public readonly int $ttl = 0,
        public readonly int $maxMemory = 0,
        public string|null $user = null,
        public string|null $group = null,
        private readonly \Closure|null $onStart = null,
        private readonly \Closure|null $onStop = null,
        private readonly \Closure|null $onReload = null,
    ) {
    }

    /**
     * @internal
     */
    final public function preInitWorker(LoggerInterface $logger, mixed $parentSocket): self
    {
        \cli_set_process_title(sprintf('PHPRunner: worker process  %s', $this->name));

        $this->logger = $logger;
        $this->parentSocket = $parentSocket;

        return $this;
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
        $this->startedAt = new \DateTimeImmutable('now');

        // Init new event loop for worker process
        $this->eventLoop = (new DriverFactory())->create();
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));

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
        foreach ([SIGTERM, SIGUSR1] as $signo) {
            $this->eventLoop->onSignal($signo, function (string $id, int $signo): void {
                match ($signo) {
                    SIGTERM => $this->stop(),
                    SIGUSR1 => $this->pipeStatus(),
                };
            });
        }
    }

    private function setUserAndGroup(): void
    {
        $currentUser = Functions::getCurrentUser();
        $this->user ??= $currentUser;

        if (\posix_getuid() !== 0 && $this->user !== $currentUser) {
            $this->logger->warning('You must have the root privileges to change the user and group', ['worker' => $this->name]);
            return;
        }

        // Get uid
        if ($userInfo = \posix_getpwnam($this->user)) {
            $uid = $userInfo['uid'];
        } else {
            $this->logger->warning(sprintf('User "%s" does not exist', $this->user), ['worker' => $this->name]);
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

    private function pipeStatus(): void
    {
        Functions::streamWrite($this->parentSocket, \serialize(new WorkerProcessStatus(
            pid: \posix_getpid(),
            user: $this->user,
            memory: \memory_get_usage(),
            name: $this->name,
            startedAt: $this->startedAt,
        )));
    }
}
