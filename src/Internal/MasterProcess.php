<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

use Luzrain\PhpRunner\Console\StdoutHandler;
use Luzrain\PhpRunner\Exception\PhpRunnerException;
use Luzrain\PhpRunner\Internal\ProcessMessage\Message;
use Luzrain\PhpRunner\Internal\ProcessMessage\ProcessInfo;
use Luzrain\PhpRunner\Internal\ProcessMessage\ProcessStatus;
use Luzrain\PhpRunner\Internal\Status\MasterProcessStatus;
use Luzrain\PhpRunner\Internal\Status\WorkerStatus;
use Luzrain\PhpRunner\Server;
use Luzrain\PhpRunner\WorkerProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;

/**
 * @internal
 */
final class MasterProcess
{
    private const STATUS_STARTING = 1;
    private const STATUS_RUNNING = 2;
    private const STATUS_SHUTDOWN = 3;
    private const GC_PERIOD = 300;

    private static bool $registered = false;
    private readonly string $startFile;
    private readonly string $pidFile;
    private readonly string $pipeFile;
    private Driver $eventLoop;
    private Suspension $suspension;
    private \DateTimeImmutable $startedAt;
    private int $status = self::STATUS_STARTING;
    private int $exitCode = 0;
    private ProcessStatusPool $processStatusPool;

    /**
     * Socket pair for send messages from childs to master process
     * @var array{0: resource, 1: resource}
     */
    private array $interProcessSocketPair;

    public function __construct(
        string|null $pidFile,
        private readonly int $stopTimeout,
        private WorkerPool $pool,
        private readonly LoggerInterface $logger,
    ) {
        if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'], true)) {
            throw new \RuntimeException('Works in command line mode only');
        }

        if (self::$registered) {
            throw new \RuntimeException('Only one instance of server can be instantiated');
        }

        StdoutHandler::register();
        ErrorHandler::register($this->logger);

        self::$registered = true;
        $this->startFile = Functions::getStartFile();

        $runDirectory = \posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir();
        $this->pidFile = $pidFile ?? \sprintf('%s/phprunner.%s.pid', $runDirectory, \hash('xxh32', $this->startFile));
        $this->pipeFile = \sprintf('%s/%s.pipe', \pathinfo($this->pidFile, PATHINFO_DIRNAME), \pathinfo($this->pidFile, PATHINFO_FILENAME));

        if (!\is_dir($pidFileDir = \dirname($this->pidFile))) {
            \mkdir(directory: $pidFileDir, recursive: true);
        }
    }

    public function run(bool $daemonize = false): int
    {
        if ($this->isRunning()) {
            $this->logger->error('Master process already running');
            return 1;
        }

        if ($daemonize && $this->daemonize()) {
            // Runs in caller process
            return 0;
        } elseif ($daemonize) {
            // Runs in daemonized master process
            StdoutHandler::reset();
        }

        $this->initServer();
        $this->saveMasterPid();
        $this->spawnWorkers();
        $this->status = self::STATUS_RUNNING;
        $this->logger->info(Server::NAME . ' has started');
        $exitCode = ($worker = $this->suspension->suspend()) ? $this->runWorker($worker) : $this->exitCode;
        isset($worker) ? exit($exitCode) : $this->onMasterShutdown();

        return $exitCode;
    }

    /**
     * Runs in master process
     *
     * @psalm-suppress InternalMethod false-positive
     */
    private function initServer(): void
    {
        \cli_set_process_title(\sprintf('%s: master process  start_file=%s', Server::NAME, $this->startFile));

        $this->startedAt = new \DateTimeImmutable('now');
        $this->interProcessSocketPair = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        \stream_set_blocking($this->interProcessSocketPair[0], false);
        $this->processStatusPool = new ProcessStatusPool();

        // Init event loop.
        // Force use StreamSelectDriver in the master process because it uses pcntl_signal to handle signals, and it works better for this case.
        $this->eventLoop = new StreamSelectDriver();
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = $this->eventLoop->getSuspension();

        $this->eventLoop->onReadable($this->interProcessSocketPair[0], function (string $id, mixed $fd) {
            while (false !== ($line = \stream_get_line($fd, 1048576, "\r\n"))) {
                $this->onMessageFromChild(\unserialize($line));
            }
        });

        $onSignal = function (string $id, int $signo): void {
            match ($signo) {
                SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT => $this->stop(),
                SIGCHLD => $this->watchChildProcesses(),
                SIGUSR1 => $this->requestProcessesStatus(),
                SIGUSR2 => $this->reload(),
            };
        };

        foreach ([SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT, SIGCHLD, SIGUSR1, SIGUSR2] as $signo) {
            $this->eventLoop->onSignal($signo, $onSignal);
        }

        // Force run garbage collection periodically
        $this->eventLoop->repeat(self::GC_PERIOD, static function (): void {
            \gc_collect_cycles();
            \gc_mem_caches();
        });
    }

    /**
     * Fork process
     *
     * @return bool return true in master process and false in child
     */
    private function daemonize(): bool
    {
        $pid = \pcntl_fork();
        if ($pid === -1) {
            throw new PhpRunnerException('Fork fail');
        }
        if ($pid > 0) {
            return true;
        }
        if (\posix_setsid() === -1) {
            throw new PhpRunnerException('Setsid fail');
        }
        return false;
    }

    private function saveMasterPid(): void
    {
        if (false === \file_put_contents($this->pidFile, (string) \posix_getpid())) {
            throw new PhpRunnerException(\sprintf('Can\'t save pid to %s', $this->pidFile));
        }

        if(!\file_exists($this->pipeFile)) {
            \posix_mkfifo($this->pipeFile, 0644);
        }
    }

    private function spawnWorkers(): void
    {
        $this->eventLoop->defer(function (): void {
            foreach ($this->pool->getWorkers() as $worker) {
                while (\iterator_count($this->pool->getAliveWorkerPids($worker)) < $worker->getCount()) {
                    if ($this->spawnWorker($worker)) {
                        return;
                    }
                }
            }
        });
    }

    private function spawnWorker(WorkerProcess $worker): bool
    {
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process
            $this->pool->addChild($worker, $pid);
            return false;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return true;
        } else {
            throw new PhpRunnerException('fork fail');
        }
    }

    private function watchChildProcesses(): void
    {
        while (($pid = \pcntl_wait($status, WNOHANG)) > 0) {
            $exitCode = \pcntl_wexitstatus($status) ?: 0;
            $this->onChildStop($pid, $exitCode);
        }
    }

    // Runs in forked process
    private function runWorker(WorkerProcess $worker): int
    {
        $this->eventLoop->stop();
        \fclose($this->interProcessSocketPair[0]);
        unset($this->suspension, $this->eventLoop, $this->pool, $this->processStatusPool, $this->interProcessSocketPair[0]);
        \gc_collect_cycles();
        \gc_mem_caches();

        return $worker->run($this->logger, $this->interProcessSocketPair[1]);
    }

    private function onMessageFromChild(Message $message): void
    {
        if ($message instanceof ProcessInfo) {
            $this->processStatusPool->addProcessInfo($message);
        } elseif ($message instanceof ProcessStatus) {
            $this->processStatusPool->addProcessStatus($message);
        }
    }

    private function onChildStop(int $pid, int $exitCode): void
    {
        $worker = $this->pool->getWorkerByPid($pid);
        $this->pool->deleteChild($pid);
        $this->processStatusPool->deleteProcess($pid);

        switch ($this->status) {
            case self::STATUS_RUNNING:
                match ($exitCode) {
                    0 => $this->logger->info(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->getName(), $pid, $exitCode)),
                    $worker::RELOAD_EXIT_CODE => $this->logger->info(\sprintf('Worker %s[pid:%d] reloaded', $worker->getName(), $pid)),
                    default => $this->logger->warning(\sprintf('Worker %s[pid:%d] exit with code %s', $worker->getName(), $pid, $exitCode)),
                };
                // Restart worker
                $this->spawnWorker($worker);
                break;
            case self::STATUS_SHUTDOWN:
                if ($this->pool->getProcessesCount() === 0) {
                    // All processes are stopped now
                    $this->logger->info(Server::NAME . ' stopped');
                    $this->suspension->resume();
                }
                break;
        }
    }

    private function onMasterShutdown(): void
    {
        if (\file_exists($this->pidFile)) {
            \unlink($this->pidFile);
        }

        if (\file_exists($this->pipeFile)) {
            \unlink($this->pipeFile);
        }
    }

    public function stop(int $code = 0): void
    {
        if (!$this->isRunning()) {
            $this->logger->error(Server::NAME . ' is not running');
            return;
        }

        $this->logger->info(Server::NAME . ' stopping ...');

        if (($masterPid = $this->getPid()) !== \posix_getpid()) {
            // If it called from outside working process
            \posix_kill($masterPid, SIGTERM);
            return;
        }

        if ($this->status === self::STATUS_SHUTDOWN) {
            return;
        }

        $this->status = self::STATUS_SHUTDOWN;
        $this->exitCode = $code;
        foreach ($this->pool->getAlivePids() as $pid) {
            \posix_kill($pid, SIGTERM);
        }
        $this->eventLoop->delay($this->stopTimeout, function (): void {
            foreach ($this->pool->getAlivePids() as $pid) {
                \posix_kill($pid, SIGKILL);
                $worker = $this->pool->getWorkerByPid($pid);
                $this->logger->notice(\sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->getName(), $pid, $this->stopTimeout));
            }
            $this->suspension->resume();
        });
    }

    public function reload(): void
    {
        if (!$this->isRunning()) {
            $this->logger->error(Server::NAME . ' is not running');
            return;
        }

        $masterPid = $this->getPid();

        if ($masterPid !== \posix_getppid()) {
            $this->logger->info(Server::NAME . ' reloading ...');
        }

        // If it called from outside working process
        if ($masterPid !== \posix_getpid()) {
            \posix_kill($masterPid, SIGUSR2);
            return;
        }

        foreach ($this->pool->getAlivePids() as $pid) {
            if ($this->processStatusPool->isDetached($pid) === false) {
                \posix_kill($pid, SIGUSR2);
            }
        }
    }

    private function getPid(): int
    {
        return \is_file($this->pidFile) ? (int) \file_get_contents($this->pidFile) : 0;
    }

    private function isRunning(): bool
    {
        if ($this->status === self::STATUS_RUNNING) {
            return true;
        }

        return !empty($this->getPid()) && \posix_kill($this->getPid(), 0);
    }

    /**
     * Send signal to all child processes and read status data from them
     */
    private function requestProcessesStatus(): void
    {
        $monitoredPids = $this->processStatusPool->getMonitoredPids();
        $receivedStatuses = 0;
        $timeoutCallbackId = '';
        $subscriber = function () use (&$subscriber, &$receivedStatuses, &$monitoredPids, &$timeoutCallbackId): void {
            if (++$receivedStatuses === \count($monitoredPids)) {
                $this->eventLoop->cancel($timeoutCallbackId);
                $this->processStatusPool->unSubscribeFromProcessStatus($subscriber);
                $this->onAllWorkersStatusReady();
            }
        };

        // Do not wait statuses from childs more than 3 seconds
        $timeoutCallbackId = $this->eventLoop->delay(3, function () use (&$subscriber) {
            $this->processStatusPool->unSubscribeFromProcessStatus($subscriber);
            $this->onAllWorkersStatusReady();
        });

        $this->processStatusPool->subscribeToProcessStatus($subscriber);

        // Send signal to child processes
        \array_walk($monitoredPids, static fn(int $pid) => \posix_kill($pid, SIGUSR1));
    }

    /**
     * When all the status data from the child processes is ready, arrange it and send to the pipe file
     */
    private function onAllWorkersStatusReady(): void
    {
        $processes = [];
        foreach ($this->pool->getAlivePids() as $pid) {
            $processes[] = $this->processStatusPool->getProcessSatus($pid);
        }

        /** @var list<WorkerStatus> $workers */
        $workers =  \array_map(fn(WorkerProcess $worker) => new WorkerStatus(
            user: $worker->getUser(),
            name: $worker->getName(),
            count: $worker->getCount(),
        ), \iterator_to_array($this->pool->getWorkers()));
        $status = new MasterProcessStatus(
            pid: \posix_getpid(),
            user: Functions::getCurrentUser(),
            memory: \memory_get_usage(),
            startedAt: $this->startedAt,
            isRunning: $this->isRunning(),
            startFile: $this->startFile,
            workers: $workers,
            processes: $processes,
        );
        $fd = \fopen($this->pipeFile, 'w');
        \fwrite($fd, \serialize($status));
        \fclose($fd);
    }

    public function getStatus(): MasterProcessStatus
    {
        if ($this->isRunning() && \file_exists($this->pipeFile)) {
            \posix_kill($this->getPid(), SIGUSR1);
            $fd = \fopen($this->pipeFile, 'r');
            \stream_set_blocking($fd, true);
            /** @var MasterProcessStatus $status */
            $status = \unserialize((string) \stream_get_contents($fd));
        } else {
            /** @var list<WorkerStatus> $workers */
            $workers = \array_map(fn(WorkerProcess $worker) => new WorkerStatus(
                user: $worker->getUser(),
                name: $worker->getName(),
                count: $worker->getCount(),
            ), \iterator_to_array($this->pool->getWorkers()));
            $status = new MasterProcessStatus(
                pid: null,
                user: Functions::getCurrentUser(),
                memory: 0,
                startedAt: null,
                isRunning: false,
                startFile: $this->startFile,
                workers: $workers,
            );
        }

        return $status;
    }
}
