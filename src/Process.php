<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Amp\DeferredFuture;
use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessHeartbeatEvent;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message\ProcessSpawnedEvent;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\MessageInterface;
use Luzrain\PHPStreamServer\MessageBus\Message\CompositeMessage;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Psr\Container\ContainerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;
use function Luzrain\PHPStreamServer\Internal\getCurrentGroup;
use function Luzrain\PHPStreamServer\Internal\getCurrentUser;

abstract class Process implements MessageBusInterface, ContainerInterface
{
    final public const HEARTBEAT_PERIOD = 2;

    private Status $status = Status::SHUTDOWN;
    private int $exitCode = 0;
    public readonly int $id;
    public readonly int $pid;
    protected readonly Container $container;
    public readonly LoggerInterface $logger;
    private readonly SocketFileMessageBus $messageBus;
    private DeferredFuture|null $startingFuture;

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
        $this->logger = $workerContainer->get('logger')->withChannel('worker');
        $this->messageBus = $workerContainer->get('bus');

        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));

        try {
            $this->setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), [(new \ReflectionObject($this))->getShortName() => $this->name]);
        }

        EventLoop::onSignal(SIGINT, static fn() => null);
        EventLoop::onSignal(SIGTERM, fn() => $this->stop());

        $heartbeatEvent = function (): ProcessHeartbeatEvent {
            return new ProcessHeartbeatEvent(
                pid: $this->pid,
                memory: \memory_get_usage(),
                time: \hrtime(true),
            );
        };

        $this->startingFuture = new DeferredFuture();

        EventLoop::repeat(self::HEARTBEAT_PERIOD, function () use ($heartbeatEvent) {
            $this->messageBus->dispatch($heartbeatEvent());
        });

        EventLoop::queue(function () use ($heartbeatEvent): void {
            $this->messageBus->dispatch(new CompositeMessage([
                new ProcessSpawnedEvent(
                    workerId: $this->id,
                    pid: $this->pid,
                    user: $this->getUser(),
                    name: $this->name,
                    startedAt: new \DateTimeImmutable('now'),
                ),
                $heartbeatEvent(),
            ]))->await();
            $this->start();
            if ($this->onStart !== null) {
                ($this->onStart)($this);
            }
            $this->status = Status::RUNNING;
            $this->startingFuture->complete();
            $this->startingFuture = null;
        });

        EventLoop::run();

        return $this->exitCode;
    }

    abstract protected function start(): void;

    /**
     * @return list<class-string<Plugin>>
     */
    abstract static public function handleBy(): array;

    final public function getUser(): string
    {
        return $this->user ?? getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? getCurrentGroup();
    }

    /**
     * @template T
     * @param MessageInterface<T> $message
     * @return Future<T>
     */
    public function dispatch(MessageInterface $message): Future
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
            $this->startingFuture?->getFuture()->await();
            $this->messageBus->stop()->await();
            if ($this->onStop !== null) {
                ($this->onStop)($this);
            }
            EventLoop::getDriver()->stop();
        });
    }

    /**
     * @throws UserChangeException
     */
    private function setUserAndGroup(string|null $user = null, string|null $group = null): void
    {
        if ($user === null && $group === null) {
            return;
        }

        if (\posix_getuid() !== 0) {
            throw new UserChangeException('You must have the root privileges to change the user and group');
        }

        $user ??= getCurrentUser();

        // Get uid
        if ($userInfo = \posix_getpwnam($user)) {
            $uid = $userInfo['uid'];
        } else {
            throw new UserChangeException(\sprintf('User "%s" does not exist', $user));
        }

        // Get gid
        if ($group === null) {
            $gid = $userInfo['gid'];
        } elseif ($groupInfo = \posix_getgrnam($group)) {
            $gid = $groupInfo['gid'];
        } else {
            throw new UserChangeException(\sprintf('Group "%s" does not exist', $group));
        }

        // Set uid and gid
        if ($uid !== \posix_getuid() || $gid !== \posix_getgid()) {
            if (!\posix_setgid($gid) || !\posix_initgroups($userInfo['name'], $gid) || !\posix_setuid($uid)) {
                throw new UserChangeException('Changing guid or uid fails');
            }
        }
    }

    final public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    final public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
