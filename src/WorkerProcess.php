<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Exception\UserChangeException;
use Luzrain\PhpRunner\Internal\ErrorHandler;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Internal\ProcessMessage\Message;
use Luzrain\PhpRunner\Internal\ProcessMessage\ProcessInfo;
use Luzrain\PhpRunner\Internal\ProcessMessage\ProcessStatus;
use Luzrain\PhpRunner\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PhpRunner\ReloadStrategy\TimerReloadStrategyInterface;
use Luzrain\PhpRunner\Server\Connection\ActiveConnection;
use Luzrain\PhpRunner\Server\Connection\ConnectionStatistics;
use Luzrain\PhpRunner\Server\Server;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess
{
    final public const STOP_EXIT_CODE = 0;
    final public const RELOAD_EXIT_CODE = 100;

    private LoggerInterface $logger;
    private Driver $eventLoop;
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
        private string $name = 'none',
        private int $count = 1,
        private bool $reloadable = true,
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
        private \Closure|null $onReload = null,
    ) {
        $this->listenAddressesMap = new \WeakMap();
    }

    /**
     * @internal
     */
    final public function run(LoggerInterface $logger, mixed $parentSocket): int
    {
        $this->setLogger($logger);
        $this->parentSocket = $parentSocket;
        $this->setUserAndGroup();
        $this->initWorker();
        $this->eventLoop->run();

        return $this->exitCode;
    }

    final public function startServer(Server $server): void
    {
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
            if ($reloadStrategy instanceof TimerReloadStrategyInterface) {
                $this->eventLoop->repeat($reloadStrategy->getInterval(), function () use ($reloadStrategy): void {
                    $reloadStrategy->shouldReload($reloadStrategy::EVENT_CODE_TIMER) && $this->reload($reloadStrategy::EXIT_CODE);
                });
            }
        }
    }

    final public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    final public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    final public function getEventLoop(): Driver
    {
        return $this->eventLoop;
    }

    final public function getName(): string
    {
        return $this->name;
    }

    final public function getCount(): int
    {
        return $this->count;
    }

    final public function isReloadable(): bool
    {
        return $this->reloadable;
    }

    final public function getUser(): string
    {
        return $this->user ?? Functions::getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? Functions::getCurrentGroup();
    }

    /**
     * @param \Closure(\Throwable):void $errorHandler
     */
    final public function setErrorHandler(\Closure $errorHandler): void
    {
        $this->eventLoop->setErrorHandler(function (\Throwable $e) use ($errorHandler) {
            $errorHandler($e);
            foreach ($this->reloadStrategies as $reloadStrategy) {
                if ($reloadStrategy->shouldReload($reloadStrategy::EVENT_CODE_EXCEPTION, $e)) {
                    $this->eventLoop->defer(function () use ($reloadStrategy): void {
                        $this->reload($reloadStrategy::EXIT_CODE);
                    });
                    break;
                }
            }
        });
    }

    private function setUserAndGroup(): void
    {
        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->getLogger()->warning($e->getMessage(), ['worker' => $this->getName()]);
            $this->user = Functions::getCurrentUser();
        }
    }

    private function initWorker(): void
    {
        \cli_set_process_title(\sprintf('%s: worker process  %s', PhpRunner::NAME, $this->getName()));

        $this->startedAt = new \DateTimeImmutable('now');

        /** @psalm-suppress InaccessibleProperty */
        $this->eventLoop = (new DriverFactory())->create();

        $this->setErrorHandler(ErrorHandler::handleException(...));

        // onStart callback
        $this->eventLoop->defer(function (): void {
            $this->onStart !== null && ($this->onStart)($this);
        });

        $onSignal = function (string $id, int $signo): void {
            match ($signo) {
                SIGTERM => $this->stop(),
                SIGUSR1 => $this->updateStatus(),
                SIGUSR2 => $this->reload(),
            };
        };

        foreach ([SIGTERM, SIGUSR1, SIGUSR2] as $signo) {
            $this->eventLoop->onSignal($signo, $onSignal);
        }

        $this->sendMessageToMaster(new ProcessInfo(\posix_getpid(), $this->getName(), $this->getUser(), $this->startedAt, false));
    }

    final public function stop(int $code = self::STOP_EXIT_CODE): void
    {
        $this->exitCode = $code;
        try {
            $this->onStop !== null && ($this->onStop)($this);
        } finally {
            $this->eventLoop->stop();
        }
    }

    final public function reload(int $code = self::RELOAD_EXIT_CODE): void
    {
        if ($this->isReloadable()) {
            $this->exitCode = $code;
            try {
                $this->onReload !== null && ($this->onReload)($this);
            } finally {
                $this->eventLoop->stop();
            }
        }
    }

    /**
     * After the process is detached, only the basic supervisor will work for it.
     * The event loop and communication with the master process will be stopped and destroyed.
     * This can be useful to give control to an external program and have it monitored by the master process.
     */
    final public function detach(): void
    {
        $identifiers = $this->getEventLoop()->getIdentifiers();
        \array_walk($identifiers, $this->getEventLoop()->disable(...));
        $this->getEventLoop()->stop();
        $this->sendMessageToMaster(new ProcessInfo(\posix_getpid(), $this->getName(), $this->getUser(), $this->startedAt, true));
        unset($this->eventLoop, $this->reloadStrategies, $this->logger, $this->parentSocket);
        $this->onStart = null;
        $this->onStop = null;
        $this->onReload = null;
        \gc_collect_cycles();
    }

    private function updateStatus(): void
    {
        $this->sendMessageToMaster(new ProcessStatus(
            pid: \posix_getpid(),
            memory: \memory_get_usage(),
            listen: \implode(', ', \iterator_to_array($this->listenAddressesMap, false)),
            connectionStatistics: ConnectionStatistics::getGlobal(),
            connections: ActiveConnection::getList(),
        ));
    }

    private function sendMessageToMaster(Message $message): void
    {
        \fwrite($this->parentSocket, \serialize($message) . "\r\n");
    }
}
