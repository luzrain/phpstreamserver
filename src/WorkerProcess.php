<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\InterprocessPipe;
use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connections;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Spawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

class WorkerProcess
{
    final public const STOP_EXIT_CODE = 0;
    final public const RELOAD_EXIT_CODE = 100;
    private const GC_PERIOD = 180;
    public const HEARTBEAT_PERIOD = 3;

    public readonly int $id;
    public readonly int $pid;
    private int $exitCode = 0;
    public LoggerInterface $logger;
    private Driver $eventLoop;
    private TrafficStatus $trafficStatisticStore;
    private ReloadStrategyTrigger $reloadStrategyTrigger;
    private InterprocessPipe $pipe;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     */
    public function __construct(
        public readonly string $name = 'none',
        public readonly int $count = 1,
        private readonly bool $reloadable = true,
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
        private \Closure|null $onReload = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
    }

    /**
     * @param resource $workerPipeResource
     * @internal
     */
    final public function run(LoggerInterface $logger, mixed $workerPipeResource): int
    {
        $this->logger = $logger;
        $this->setUserAndGroup();
        $this->initWorker($workerPipeResource);
        $this->eventLoop->run();

        return $this->exitCode;
    }

    private function setUserAndGroup(): void
    {
        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), ['worker' => $this->name]);
            $this->user = Functions::getCurrentUser();
        }
    }

    /**
     * @param resource $workerPipeResource
     */
    private function initWorker(mixed $workerPipeResource): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: worker process  %s', Server::NAME, $this->name));
        }

        /** @psalm-suppress InaccessibleProperty */
        $this->eventLoop = (new DriverFactory())->create();
        EventLoop::setDriver($this->eventLoop);
        $this->setErrorHandler(ErrorHandler::handleException(...));
        /** @psalm-suppress InaccessibleProperty */
        $this->pid = \posix_getpid();
        $this->pipe = new InterprocessPipe($workerPipeResource);

        // onStart callback
        $this->eventLoop->defer(function (): void {
            $this->onStart !== null && ($this->onStart)($this);
        });

        $this->eventLoop->onSignal(SIGTERM, fn() => $this->stop());
        $this->eventLoop->onSignal(SIGUSR1, fn() => $this->reload());
        $this->eventLoop->onSignal(SIGUSR2, fn() => $this->pipe->publish(new Connections($this->trafficStatisticStore->getConnections())));

        // Force run garbage collection periodically
        $this->eventLoop->repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });

        $this->trafficStatisticStore = new TrafficStatus($this->pipe);
        $this->reloadStrategyTrigger = new ReloadStrategyTrigger($this->eventLoop, $this->reload(...));

        $this->pipe->publish(new Spawn(
            pid: $this->pid,
            user: $this->getUser(),
            name: $this->name,
            startedAt: new \DateTimeImmutable('now'),
        ));

        $this->pipe->publish(new Heartbeat($this->pid, \memory_get_usage(), \hrtime(true)));
        $this->eventLoop->repeat(self::HEARTBEAT_PERIOD, fn() => $this->pipe->publish(new Heartbeat($this->pid, \memory_get_usage(), \hrtime(true))));
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
        if (!$this->reloadable) {
            return;
        }

        $this->exitCode = self::RELOAD_EXIT_CODE;
        try {
            $this->onReload !== null && ($this->onReload)($this);
        } finally {
            $this->eventLoop->stop();
        }
    }

    public function detach(): void
    {
        $identifiers = $this->eventLoop->getIdentifiers();
        \array_walk($identifiers, $this->eventLoop->cancel(...));
        $this->eventLoop->stop();
        $this->pipe->publish(new Detach($this->pid));
        unset($this->trafficStatisticStore, $this->reloadStrategyTrigger, $this->pipe);
        $this->onStart = null;
        $this->onStop = null;
        $this->onReload = null;
        \gc_collect_cycles();
        \gc_mem_caches();
    }

    /**
     * Give control to an external program and have it monitored by the master process.
     *
     * @param string $path path to a binary executable or a script
     * @param array $args array of argument strings passed to the program
     * @see https://www.php.net/manual/en/function.pcntl-exec.php
     */
    public function exec(string $path, array $args = []): never
    {
        $this->detach();
        $envVars = [...\getenv(), ...$_ENV];
        \pcntl_exec($path, $args, $envVars);
        exit(0);
    }

    final public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param \Closure(\Throwable):void $errorHandler
     */
    final public function setErrorHandler(\Closure $errorHandler): void
    {
        $this->eventLoop->setErrorHandler(function (\Throwable $exception) use ($errorHandler) {
            $errorHandler($exception);
            $this->reloadStrategyTrigger->emitException($exception);
        });
    }

    final public function addReloadStrategies(ReloadStrategy ...$reloadStrategies): void
    {
        $this->reloadStrategyTrigger->addReloadStrategies(...$reloadStrategies);
    }

    final public function startPlugin(Plugin $plugin): void
    {
        $plugin->start($this->logger, $this->trafficStatisticStore, $this->reloadStrategyTrigger);
    }

    final public function getUser(): string
    {
        return $this->user ?? Functions::getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? Functions::getCurrentGroup();
    }
}
