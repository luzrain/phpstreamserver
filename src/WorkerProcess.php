<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\ProcessMessage\Message;
use Luzrain\PHPStreamServer\Internal\ProcessMessage\ProcessInfo;
use Luzrain\PHPStreamServer\Internal\ProcessMessage\ProcessStatus;
use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategyInterface;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionStatistics;
use Luzrain\PHPStreamServer\Server\TrafficStatisticStore;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess
{
    final public const STOP_EXIT_CODE = 0;
    final public const RELOAD_EXIT_CODE = 100;
    private const GC_PERIOD = 180;

    private LoggerInterface $logger;
    private Driver $eventLoop;
    private \DateTimeImmutable $startedAt;
    /** @var resource parent socket for interprocess communication */
    private mixed $parentSocket;
    private int $exitCode = 0;
    private \WeakMap $listenAddressesMap;
    private TrafficStatisticStore $trafficStatisticStore;
    private ReloadStrategyTrigger $reloadStrategyTrigger;

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
        $this->trafficStatisticStore = new TrafficStatisticStore();
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

    final public function startHttpServer(HttpServer $server): void
    {
        $server->start($this->logger, $this->trafficStatisticStore, $this->reloadStrategyTrigger);
    }

    final public function addReloadStrategies(ReloadStrategyInterface ...$reloadStrategies): void
    {
        $this->reloadStrategyTrigger->addReloadStrategies(...$reloadStrategies);
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
        $this->eventLoop->setErrorHandler(function (\Throwable $exception) use ($errorHandler) {
            $errorHandler($exception);
            $this->reloadStrategyTrigger->exception($exception);
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
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: worker process  %s', Server::NAME, $this->getName()));
        }

        $this->startedAt = new \DateTimeImmutable('now');

        /** @psalm-suppress InaccessibleProperty */
        $this->eventLoop = (new DriverFactory())->create();
        EventLoop::setDriver($this->eventLoop);
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

        // Force run garbage collection periodically
        $this->eventLoop->repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });

        $this->reloadStrategyTrigger = new ReloadStrategyTrigger($this->eventLoop, $this->reload(...));

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

    final public function reload(): void
    {
        if (!$this->isReloadable()) {
            return;
        }

        $this->exitCode = self::RELOAD_EXIT_CODE;
        try {
            $this->onReload !== null && ($this->onReload)($this);
        } finally {
            $this->eventLoop->stop();
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
        \gc_mem_caches();
    }

    private function updateStatus(): void
    {
        $this->sendMessageToMaster(new ProcessStatus(
            pid: \posix_getpid(),
            memory: \memory_get_usage(),
            listen: \implode(', ', \iterator_to_array($this->listenAddressesMap, false)),
            connectionStatistics: ConnectionStatistics::getGlobal(),
            connections: $this->trafficStatisticStore->getConnections(),
        ));
    }

    private function sendMessageToMaster(Message $message): void
    {
        \fwrite($this->parentSocket, \serialize($message) . "\r\n");
    }
}
