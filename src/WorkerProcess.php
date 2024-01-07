<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Exception\UserChangeException;
use Luzrain\PhpRunner\Internal\ErrorHandler;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Server\Server;
use Luzrain\PhpRunner\Status\WorkerProcessStatus;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess
{
    final public const RELOAD_EXIT_CODE = 100;
    final public const TTL_EXIT_CODE = 101;
    final public const MAX_MEMORY_EXIT_CODE = 102;

    protected readonly LoggerInterface $logger;
    protected readonly Driver $eventLoop;
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
        private readonly Server|null $server = null,
    ) {
        $this->eventLoop = (new DriverFactory())->create();
    }

    /**
     * @internal
     */
    final public function setDependencies(LoggerInterface $logger, mixed $parentSocket): self
    {
        /** @psalm-suppress InaccessibleProperty */
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
        $this->server?->start($this->eventLoop);
        $this->eventLoop->run();

        return $this->exitCode;
    }

    private function setUserAndGroup(): void
    {
        $currentUser = Functions::getCurrentUser();
        $this->user ??= $currentUser;

        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), ['worker' => $this->name]);
            $this->user = $currentUser;
        }
    }

    private function initWorker(): void
    {
        \cli_set_process_title(\sprintf('PHPRunner: worker process  %s', $this->name));

        $this->startedAt = new \DateTimeImmutable('now');
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));

        // onStart callback
        $this->eventLoop->defer(function (): void {
            if($this->onStart !== null) {
                ($this->onStart)();
            }
        });

        // Watch ttl
        if ($this->ttl > 0) {
            $this->eventLoop->delay($this->ttl, function (): void {
                $this->stop(self::TTL_EXIT_CODE);
            });
        }

        // Watch max memory
        if ($this->maxMemory > 0) {
            $this->eventLoop->repeat(15, function (): void {
                if (\max(\memory_get_peak_usage(), \memory_get_usage()) > $this->maxMemory) {
                    $this->stop(self::MAX_MEMORY_EXIT_CODE);
                }
            });
        }
    }

    private function initSignalHandler(): void
    {
        foreach ([SIGTERM, SIGUSR1, SIGUSR2] as $signo) {
            $this->eventLoop->onSignal($signo, function (string $id, int $signo): void {
                match ($signo) {
                    SIGTERM => $this->stop(),
                    SIGUSR1 => $this->pipeStatus(),
                    SIGUSR2 => $this->reload(),
                };
            });
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

    private function reload(): void
    {
        if (!$this->reloadable) {
            return;
        }

        $this->exitCode = self::RELOAD_EXIT_CODE;
        try {
            if($this->onReload !== null) {
                ($this->onReload)();
            }
        } finally {
            $this->eventLoop->stop();
        }
    }

    private function pipeStatus(): void
    {
        Functions::streamWrite($this->parentSocket, \serialize(new WorkerProcessStatus(
            pid: \posix_getpid(),
            user: $this->user ?? '',
            memory: \memory_get_usage(),
            name: $this->name,
            startedAt: $this->startedAt,
        )));
    }
}
