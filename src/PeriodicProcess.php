<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Internal\ErrorHandler;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\Internal\MessageBus\SocketFileMessageBus;
use Luzrain\PHPStreamServer\Internal\ProcessTrait;
use Revolt\EventLoop;
use Revolt\EventLoop\DriverFactory;

final class PeriodicProcess implements PeriodicProcessInterface
{
    use ProcessTrait {
        detach as detachByTrait;
    }

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
    public function __construct(
        string $name = 'none',
        public readonly string $schedule = '1 minute',
        public readonly int $jitter = 0,
        string|null $user = null,
        string|null $group = null,
        private \Closure|null $onStart = null,
        private \Closure|null $onStop = null,
    ) {
        static $nextId = 0;
        $this->id = ++$nextId;
        $this->name = $name;
        $this->user = $user;
        $this->group = $group;
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
        $this->detachByTrait();
        unset($this->messageBus);
        $this->onStart = null;
        $this->onStop = null;
        \gc_collect_cycles();
        \gc_mem_caches();
    }
}
