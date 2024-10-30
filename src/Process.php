<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\Status;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

abstract class Process implements MessageBus
{
    final public const HEARTBEAT_PERIOD = 2;

    private Status $status = Status::SHUTDOWN;
    private int $exitCode = 0;
    public readonly int $id;
    public readonly int $pid;
    protected readonly Container $container;
    public readonly LoggerInterface $logger;
    private readonly SocketFileMessageBus $messageBus;
    private readonly DeferredFuture $startingFuture;

    /**
     * @param null|\Closure(self):void $onStart
     * @param null|\Closure(self):void $onStop
     */
    public function __construct(
        public readonly string $name = 'none',
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
    }

    /**
     * @internal
     */
    final public function run(Container $workerContainer): int
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: worker process  %s', Server::NAME, $this->name));
        }

        EventLoop::setDriver((new DriverFactory())->create());

        $this->status = Status::STARTING;
        $this->pid = \posix_getpid();
        $this->container = $workerContainer;
        $this->logger = $workerContainer->get('logger');
        $this->messageBus = $workerContainer->get('bus');

        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));

        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), [(new \ReflectionObject($this))->getShortName() => $this->name]);
        }

        EventLoop::onSignal(SIGINT, static fn() => null);
        EventLoop::onSignal(SIGTERM, fn() => $this->stop());

        $this->start();

        $spawnedEvent = function (): void {
            $this->messageBus->dispatch(new ProcessSpawnedEvent(
                workerId: $this->id,
                pid: $this->pid,
                user: $this->getUser(),
                name: $this->name,
                startedAt: new \DateTimeImmutable('now'),
            ));
        };

        $heartbeatEvent = function (): void {
            $this->messageBus->dispatch(new ProcessHeartbeatEvent(
                pid: $this->pid,
                memory: \memory_get_usage(),
                time: \hrtime(true),
            ));
        };

        $this->startingFuture = new DeferredFuture();

        EventLoop::repeat(self::HEARTBEAT_PERIOD, $heartbeatEvent);

        EventLoop::defer(function () use ($spawnedEvent, $heartbeatEvent): void {
            $spawnedEvent();
            $heartbeatEvent();
            if ($this->onStart !== null) {
                ($this->onStart)($this);
            }
            $this->status = Status::RUNNING;
            $this->startingFuture->complete();
        });

        EventLoop::run();

        return $this->exitCode;
    }

    abstract protected function start(): void;

    /**
     * @return list<class-string<Plugin>>
     */
    abstract static public function handleBy(): array;

    /**
     * Stop and destroy the process event loop and communication with the master process.
     * After the process is detached, only the basic supervisor will work for it.
     * This can be useful to give control to an external program and have it monitored by the master process.
     */
    public function detach(): void
    {
        $identifiers = EventLoop::getDriver()->getIdentifiers();
        \array_walk($identifiers, EventLoop::getDriver()->cancel(...));
        EventLoop::getDriver()->stop();
        unset($this->logger);
        unset($this->socketFile);
    }

    /**
     * Give control to an external program
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

    final public function getUser(): string
    {
        return $this->user ?? Functions::getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? Functions::getCurrentGroup();
    }

    /**
     * @template T
     * @param Message<T> $message
     * @return Future<T>
     */
    public function dispatch(Message $message): Future
    {
        return $this->messageBus->dispatch($message);
    }

    public function stop(int $code = 0): void
    {
        if ($this->status !== Status::STARTING && $this->status !== Status::RUNNING) {
            return;
        }

        $this->status = Status::STOPPING;
        $this->exitCode = $code;

        EventLoop::defer(function (): void {
            $this->startingFuture->getFuture()->await();
            $this->messageBus->stop()->await();
            if ($this->onStop !== null) {
                ($this->onStop)($this);
            }
            EventLoop::getDriver()->stop();
        });
    }
}
