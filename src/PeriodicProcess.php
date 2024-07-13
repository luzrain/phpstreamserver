<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Internal\Relay\Relay;
use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Connections;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Detach;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Heartbeat;
use Luzrain\PHPStreamServer\Internal\ServerStatus\Message\Spawn;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;
use PHPUnit\Event\Event;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\DriverFactory;

class PeriodicProcess
{
    public readonly int $id;
    public readonly int $pid;
    public readonly LoggerInterface $logger;
    public Relay $relay;

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
    public function __construct(
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

    /**
     * @internal
     */
    final public function run(LoggerInterface $logger, Relay $relay = null): int
    {
        $this->logger = $logger;
        //$this->relay = $relay;
        $this->setUserAndGroup();
        $this->initWorker();

        // onStart callback
        $this->onStart !== null && ($this->onStart)($this);

        // onStop callback
        $this->onStop !== null && ($this->onStop)($this);

        return 0;
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
    }

    public function detach(): void
    {
        EventLoop::queue(static fn() => EventLoop::getDriver()->stop());
        if (!EventLoop::getDriver()->isRunning()) {
            EventLoop::run();
        }

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
}
