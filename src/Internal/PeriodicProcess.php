<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Amp\Future;
use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\MessageBus\Message;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\PeriodicProcessDefinition;
use Luzrain\PHPStreamServer\PeriodicProcessInterface;
use Luzrain\PHPStreamServer\Server;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

final class PeriodicProcess implements RunnableProcess, PeriodicProcessInterface
{
    public readonly int $id;
    public readonly int $pid;
    public readonly LoggerInterface $logger;
    private string $socketFile;
    private MessageBus $messageBus;

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
     * @param null|\Closure(self):void $onStop
     */
    private function __construct(
        public readonly string $schedule,
        public readonly int $jitter = 0,
        public readonly string $name = 'none',
        private string|null $user = null,
        private string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
    }

    public static function createFromDefinition(PeriodicProcessDefinition $definition): self
    {
        return new self(
            schedule: $definition->schedule,
            jitter: $definition->jitter,
            name: $definition->name,
            user: $definition->user,
            group: $definition->group,
            onStart: $definition->onStart,
            onStop: $definition->onStop,
        );
    }

    /**
     * @internal
     */
    final public function run(WorkerContext $workerContext): int
    {
        $this->socketFile = $workerContext->socketFile;
        $this->logger = $workerContext->logger;
        $this->setUserAndGroup();
        $this->initWorker();
        EventLoop::run();

        return 0;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private function setUserAndGroup(): void
    {
        try {
            Functions::setUserAndGroup($this->user, $this->group);
        } catch (UserChangeException $e) {
            $this->logger->warning($e->getMessage(), ['periodicProcess' => $this->name]);
            $this->user = Functions::getCurrentUser();
        }
    }

    private function initWorker(): void
    {
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: scheduler process  %s', Server::NAME, $this->name));
        }

        EventLoop::setDriver((new DriverFactory())->create());
        EventLoop::setErrorHandler(ErrorHandler::handleException(...));

        /** @psalm-suppress InaccessibleProperty */
        $this->pid = \posix_getpid();

        $this->messageBus = new SocketFileMessageBus($this->socketFile);

        EventLoop::defer(function (): void {
            $this->onStart !== null && ($this->onStart)($this);
            $this->onStop !== null && ($this->onStop)($this);
        });
    }

    public function detach(): void
    {
        $identifiers = EventLoop::getDriver()->getIdentifiers();
        \array_walk($identifiers, EventLoop::getDriver()->cancel(...));
        EventLoop::getDriver()->stop();
        unset($this->logger);
        unset($this->socketFile);
        unset($this->messageBus);
        $this->onStart = null;
        $this->onStop = null;
        \gc_collect_cycles();
        \gc_mem_caches();
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
}
