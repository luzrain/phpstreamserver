<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Exception\UserChangeException;
use Luzrain\PhpRunner\Internal\ErrorHandler;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PhpRunner\ReloadStrategy\TTLReloadStrategy;
use Luzrain\PhpRunner\Server\Connection\ActiveConnection;
use Luzrain\PhpRunner\Server\Connection\ConnectionStatistics;
use Luzrain\PhpRunner\Server\Server;
use Luzrain\PhpRunner\Status\WorkerProcessStatus;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess
{
    final public const STOP_EXIT_CODE = 0;
    final public const RELOAD_EXIT_CODE = 100;

    public readonly LoggerInterface $logger;
    public readonly Driver $eventLoop;
    private \DateTimeImmutable $startedAt;
    /** @var array<ReloadStrategyInterface> */
    private array $reloadStrategies = [];
    /** @var resource parent socket for interprocess communication */
    private mixed $parentSocket;
    private int $exitCode = 0;

    private \WeakMap $listenAddressesMap;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     */
    public function __construct(
        public readonly string $name = 'none',
        public readonly int $count = 1,
        public readonly bool $reloadable = true,
        public string|null $user = null,
        public string|null $group = null,
        private readonly \Closure|null $onStart = null,
        private readonly \Closure|null $onStop = null,
        private readonly \Closure|null $onReload = null,
    ) {
    }

    final public function startServer(Server $server): void
    {
        $this->listenAddressesMap ??= new \WeakMap();
        $this->listenAddressesMap[$server] = $server->getReadableListenAddress();
        $server->start($this->eventLoop, $this->reloadStrategies, $this->reload(...));
    }

    final public function stopServer(Server $server): void
    {
        $server->stop();
    }

    final public function addReloadStrategies(ReloadStrategyInterface ...$reloadStrategies): void
    {
        \array_push($this->reloadStrategies, ...$reloadStrategies);
        foreach ($reloadStrategies as $reloadStrategy) {
            if ($reloadStrategy instanceof TTLReloadStrategy) {
                $this->eventLoop->delay($reloadStrategy->ttl, function () use ($reloadStrategy): void {
                    $this->reload($reloadStrategy::EXIT_CODE);
                });
            }
            if ($reloadStrategy->onTimer()) {
                $this->eventLoop->repeat(ReloadStrategyInterface::TIMER_INTERVAL, function () use ($reloadStrategy): void {
                    $reloadStrategy->shouldReload() && $this->reload($reloadStrategy::EXIT_CODE);
                });
            }
        }
    }

    /**
     * @internal
     */
    final public function run(LoggerInterface $logger, mixed $parentSocket): int
    {
        /** @psalm-suppress InaccessibleProperty */
        $this->logger = $logger;
        $this->parentSocket = $parentSocket;
        $this->setUserAndGroup();
        $this->initWorker();
        $this->initSignalHandler();
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
        \cli_set_process_title(\sprintf('%s: worker process  %s', PhpRunner::NAME, $this->name));

        $this->startedAt = new \DateTimeImmutable('now');

        /** @psalm-suppress InaccessibleProperty */
        $this->eventLoop = (new DriverFactory())->create();

        $this->eventLoop->setErrorHandler(function (\Throwable $e) {
            ErrorHandler::handleException($e);
            foreach ($this->reloadStrategies as $reloadStrategy) {
                if ($reloadStrategy->onException() && $reloadStrategy->shouldReload($e)) {
                    $this->eventLoop->defer(function () use ($reloadStrategy): void {
                        $this->reload($reloadStrategy::EXIT_CODE);
                    });
                }
            }
        });

        // onStart callback
        $this->eventLoop->defer(function (): void {
            $this->onStart !== null && ($this->onStart)($this);
        });
    }

    private function initSignalHandler(): void
    {
        foreach ([SIGTERM, SIGUSR1, SIGUSR2] as $signo) {
            $this->eventLoop->onSignal($signo, function (string $id, int $signo): void {
                match ($signo) {
                    SIGTERM => $this->stop(),
                    SIGUSR1 => $this->uploadStatus(),
                    SIGUSR2 => $this->reload(),
                };
            });
        }
    }

    private function stop(int $code = self::STOP_EXIT_CODE): void
    {
        $this->exitCode = $code;
        try {
            $this->onStop !== null && ($this->onStop)($this);
        } finally {
            $this->eventLoop->stop();
        }
    }

    private function reload(int $code = self::RELOAD_EXIT_CODE): void
    {
        if ($this->reloadable) {
            $this->exitCode = $code;
            try {
                $this->onReload !== null && ($this->onReload)($this);
            } finally {
                $this->eventLoop->stop();
            }
        }
    }

    private function uploadStatus(): void
    {
        Functions::streamWrite($this->parentSocket, \serialize(new WorkerProcessStatus(
            pid: \posix_getpid(),
            user: $this->user ?? '',
            memory: \memory_get_usage(),
            name: $this->name,
            startedAt: $this->startedAt,
            listen: implode(', ', iterator_to_array($this->listenAddressesMap ?? [], false)),
            connectionStatistics: ConnectionStatistics::getGlobal(),
            connections: ActiveConnection::getList(),
        )));
    }
}
