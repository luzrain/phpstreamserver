<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Exception\PHPRunnerException;
use Luzrain\PhpRunner\Internal\ErrorHandler;
use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Status\MasterProcessStatus;
use Luzrain\PhpRunner\Status\WorkerProcessStatus;
use Luzrain\PhpRunner\Status\WorkerStatus;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;

final class MasterProcess
{
    public const STATUS_STARTING = 1;
    public const STATUS_RUNNING = 2;
    public const STATUS_SHUTDOWN = 3;
    public const STATUS_RELOADING = 4;

    private static bool $registered = false;
    private readonly string $startFile;
    private readonly string $pidFile;
    private readonly string $pipeFile;
    private Driver $eventLoop;
    private Suspension $suspension;
    private \DateTimeImmutable $startedAt;
    private int $status = self::STATUS_STARTING;
    private int $exitCode = 0;

    public function __construct(
        private WorkerPool $pool,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
        if (!\in_array(PHP_SAPI, ['cli', 'phpdbg', 'micro'])) {
            throw new \RuntimeException('Works in command line mode only');
        }

        if (self::$registered) {
            throw new \RuntimeException('Only one instance of server can be instantiated');
        }

        self::$registered = true;
        $this->startFile = Functions::getStartFile();
        $this->pidFile = $this->config->pidFile ?? \sprintf('%s/phprunner.%s.pid', \sys_get_temp_dir(), \hash('xxh32', $this->startFile));
        $this->pipeFile = sprintf('%s/%s.pipe', \pathinfo($this->pidFile, PATHINFO_DIRNAME), \pathinfo($this->pidFile, PATHINFO_FILENAME));
    }

    public function run(bool $isDaemon = false): never
    {
        if ($this->isRunning()) {
            $this->logger->error('Master process already running');
            exit;
        }

        $this->initServer();
        $this->saveMasterPid();
        $this->createMasterPipe();
        $this->initSignalHandler();
        $this->spawnWorkers();
        $this->status = self::STATUS_RUNNING;
        $exitCode = ([$worker, $parentSocket] = $this->suspension->suspend())
            ? $this->preInitWorker($worker, $parentSocket)->run()
            : $this->exitCode;

        if (!isset($worker)) {
            $this->onMasterShutdown();
        }

        exit($exitCode);
    }

    // Runs in master process
    private function initServer(): void
    {
        \cli_set_process_title(sprintf('PHPRunner: master process  start_file=%s', $this->startFile));

        $this->startedAt = new \DateTimeImmutable('now');

        // Init event loop.
        // Force use StreamSelectDriver in the master process because it uses pcntl_signal to handle signals, and it works better for this case.
        $this->eventLoop = new StreamSelectDriver();
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = $this->eventLoop->getSuspension();
    }

    private function saveMasterPid(): void
    {
        if (false === \file_put_contents($this->pidFile, \posix_getpid())) {
            throw new PHPRunnerException(\sprintf('Can\'t save pid to %s', $this->pidFile));
        }
    }

    private function createMasterPipe(): void
    {
        if(!\file_exists($this->pipeFile)) {
            \posix_mkfifo($this->pipeFile, 0644);
        }
    }

    private function initSignalHandler(): void
    {
        foreach ([SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT, SIGCHLD, SIGUSR1] as $signo) {
            $this->eventLoop->onSignal($signo, function (string $id, int $signo): void {
                match ($signo) {
                    SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT => $this->stop(),
                    SIGCHLD => $this->watchWorkers(),
                    SIGUSR1 => $this->pipeStatus(),
                };
            });
        }
    }

    private function spawnWorkers(): void
    {
        $this->eventLoop->defer(function (): void {
            foreach ($this->pool->getWorkers() as $worker) {
                while (iterator_count($this->pool->getAliveWorkerPids($worker)) < $worker->count) {
                    if ($this->spawnWorker($worker)) {
                        return;
                    }
                }
            }
        });
    }

    private function spawnWorker(WorkerProcess $worker): bool
    {
        $pair = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = \pcntl_fork();
        if ($pid > 0) {
            // Master process.
            fclose($pair[0]);
            unset($pair[0]);
            $this->pool->addChild($worker, $pid, $pair[1]);
            return false;
        } elseif ($pid === 0) {
            // Child process
            fclose($pair[1]);
            unset($pair[1]);
            $this->suspension->resume([$worker, $pair[0]]);
            return true;
        } else {
            throw new PHPRunnerException('fork fail');
        }
    }

    private function watchWorkers(): void
    {
        $status = 0;
        while (($pid = \pcntl_wait($status, WNOHANG)) > 0) {
            $exitCode = \pcntl_wexitstatus($status) ?: 0;
            $worker = $this->pool->getWorkerByPid($pid);
            $this->pool->deleteChild($pid);
            $this->onWorkerStop($worker, $pid, $exitCode);
        }
    }

    /**
     * Runs in forked process
     * @param resource $parentSocket
     */
    private function preInitWorker(WorkerProcess $worker, mixed $parentSocket): WorkerProcess
    {
        $this->eventLoop->stop();
        unset($this->suspension, $this->eventLoop, $this->pool);

        return $worker->preInitWorker($this->logger, $parentSocket);
    }

    private function onWorkerStop(WorkerProcess $worker, int $pid, int $exitCode): void
    {
        switch ($this->status) {
            case self::STATUS_SHUTDOWN:
                if ($this->pool->getProcessesCount() === 0) {
                    // All processes are stopped now
                    $this->logger->info('PHPRunner stopped');
                    $this->suspension->resume();
                }
                break;
            case self::STATUS_RUNNING:
                $this->logger->notice(sprintf('Worker %s[pid:%d] exit with code %s', $worker->name, $pid, $exitCode));
                // Restart worker
                $this->spawnWorker($worker);
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
        if ($this->status === self::STATUS_SHUTDOWN) {
            return;
        }
        $this->status = self::STATUS_SHUTDOWN;
        $this->exitCode = $code;
        $this->logger->info('PHPRunner stopping ...');
        foreach ($this->pool->getAlivePids() as $pid) {
            \posix_kill($pid, SIGTERM);
        }
        $this->eventLoop->delay($this->config->stopTimeout, function () {
            foreach ($this->pool->getAlivePids() as $pid) {
                \posix_kill($pid, SIGKILL);
                $worker = $this->pool->getWorkerByPid($pid);
                $this->logger->notice(sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->name, $pid, $this->config->stopTimeout));
            }
            $this->suspension->resume();
        });
    }

    private function getMasterPid(): int
    {
        return \is_file($this->pidFile) ? (int)\file_get_contents($this->pidFile) : 0;
    }

    private function isRunning(): bool
    {
        $masterPid = $this->getMasterPid();
        return !empty($masterPid) && \posix_getpid() !== $masterPid && \posix_kill($masterPid, 0);
    }

    private function pipeStatus(): void
    {
        $pids = \iterator_to_array($this->pool->getAlivePids());
        $callbackIds = [];
        $data = [];
        $fallbackId = '';

        foreach ($pids as $pid) {
            $fd = $this->pool->getChildSocketByPid($pid);
            $callbackIds[] = $this->eventLoop->onReadable($fd, function (string $id, mixed $fd) use (&$pids, &$callbackIds, &$data, &$fallbackId) {
                $this->eventLoop->cancel($id);
                $dataReceived = Functions::streamRead($fd);
                if ($dataReceived !== '') {
                    $data[] = \unserialize($dataReceived);
                }
                if (\count($data) === \count($pids)) {
                    $this->eventLoop->cancel($fallbackId);
                    $this->onAllWorkersStatusReady($data);
                    unset($pids, $callbackIds, $data, $fallbackId);
                }
            });
        }

        $fallbackId = $this->eventLoop->delay(4, function () use (&$pids, &$callbackIds, &$data, &$fallbackId) {
            \array_walk($callbackIds, $this->eventLoop->cancel(...));
            $this->onAllWorkersStatusReady($data);
            unset($pids, $callbackIds, $data, $fallbackId);
        });

        \array_walk($pids, fn ($pid) => \posix_kill($pid, SIGUSR1));
    }

    /**
     * @param list<WorkerProcessStatus> $processes
     */
    private function onAllWorkersStatusReady(array $processes): void
    {
        $status = new MasterProcessStatus(
            pid: \posix_getpid(),
            user: Functions::getCurrentUser(),
            memory: \memory_get_usage(),
            startedAt: $this->startedAt,
            status: $this->status,
            startFile: $this->startFile,
            workers: array_map(fn (WorkerProcess $worker) => new WorkerStatus(
                user: $worker->user ?? Functions::getCurrentUser(),
                name: $worker->name,
                count: $worker->count,
            ), \iterator_to_array($this->pool->getWorkers())),
            processes: $processes,
        );

        $fd = \fopen($this->pipeFile, 'w');
        Functions::streamWrite($fd, \serialize($status));
    }

    public function getStatus(): MasterProcessStatus
    {
        if ($this->isRunning() && \file_exists($this->pipeFile)) {
            \posix_kill($this->getMasterPid(), SIGUSR1);
            $fd = \fopen($this->pipeFile, 'r');
            $data = Functions::streamRead($fd, true);
            /** @var MasterProcessStatus $status */
            $status = \unserialize($data);
        } else {
            $status = new MasterProcessStatus(
                pid: 0,
                user: Functions::getCurrentUser(),
                memory: 0,
                startedAt: null,
                status: self::STATUS_SHUTDOWN,
                startFile: $this->startFile,
                workers: \array_map(fn (WorkerProcess $worker) => new WorkerStatus(
                    user: $worker->user ?? Functions::getCurrentUser(),
                    name: $worker->name,
                    count: $worker->count,
                ), \iterator_to_array($this->pool->getWorkers())),
            );
        }

        return $status;
    }
}
