<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Scheduler;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageInterface;
use Luzrain\PHPStreamServer\Process;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\Worker\LoggerInterface;
use Luzrain\PHPStreamServer\Worker\ProcessUserChange;
use Luzrain\PHPStreamServer\Worker\Status;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;
use function Luzrain\PHPStreamServer\Internal\getCurrentGroup;
use function Luzrain\PHPStreamServer\Internal\getCurrentUser;

class PeriodicProcess implements Process, MessageBusInterface
{
    use ProcessUserChange;

    private Status $status = Status::SHUTDOWN;
    private int $exitCode = 0;
    public readonly int $id;
    public readonly int $pid;
    protected readonly Container $container;
    public readonly LoggerInterface $logger;
    private readonly SocketFileMessageBus $messageBus;

    /**
     * $schedule can be one of the following formats:
     *  - Number of seconds
     *  - An ISO8601 datetime format
     *  - An ISO8601 duration format
     *  - A relative date format as supported by \DateInterval
     *  - A cron expression
     *
     * @param string $schedule Schedule in one of the formats described above
     * @param int $jitter Jitter in seconds that adds a random time offset to the schedule
     * @param null|\Closure(self):void $onStart
     */
    public function __construct(
        public readonly string $name = 'none',
        public readonly string $schedule = '1 minute',
        public readonly int $jitter = 0,
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
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
            \cli_set_process_title(\sprintf('%s: perriodic process  %s', Server::NAME, $this->name));
        }

        EventLoop::setDriver((new DriverFactory())->create());

        $this->pid = \posix_getpid();
        $this->container = $workerContainer;
        $this->logger = $workerContainer->get('logger')->withChannel('worker');
        $this->messageBus = $workerContainer->get('bus');

        ErrorHandler::register($this->logger);
        EventLoop::setErrorHandler(function (\Throwable $exception) {
            ErrorHandler::handleException($exception);
            $this->exitCode = 1;
        });

        try {
            $this->setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), [(new \ReflectionObject($this))->getShortName() => $this->name]);
        }

        EventLoop::unreference(EventLoop::onSignal(SIGINT, static fn() => null));

        EventLoop::queue(function () {
            if ($this->onStart !== null) {
                ($this->onStart)($this);
            }
        });

        EventLoop::run();

        return $this->exitCode;
    }

    static public function handleBy(): array
    {
        return [SchedulerPlugin::class];
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

    final public function getUser(): string
    {
        return $this->user ?? getCurrentUser();
    }

    final public function getGroup(): string
    {
        return $this->group ?? getCurrentGroup();
    }
}
