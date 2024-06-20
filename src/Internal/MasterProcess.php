<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Console\StdoutHandler;
use Luzrain\PHPStreamServer\Exception\PHPStreamServerException;
use Luzrain\PHPStreamServer\Internal\ServerStatus\ServerStatus;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\WorkerProcess;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Driver;
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
    private int $status = self::STATUS_STARTING;
    private int $exitCode = 0;
    private InterprocessPipe $pipe;
    private ServerStatus $serverStatus;

    /**
     * @var resource $workerPipeResource
     */
    private mixed $workerPipeResource;

    public function __construct(
        string|null $pidFile,
        private readonly int $stopTimeout,
        private WorkerPool $workerPool,
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
        $this->pidFile = $pidFile ?? \sprintf('%s/phpss.%s.pid', $runDirectory, \hash('xxh32', $this->startFile));
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

        if ($daemonize && $this->doDaemonize()) {
            // Runs in caller process
            return 0;
        } elseif ($daemonize) {
            // Runs in daemonized master process
            StdoutHandler::disableStdout();
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
        // some command line SAPIs (e.g. phpdbg) don't have that function
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title(\sprintf('%s: master process  start_file=%s', Server::NAME, $this->startFile));
        }

        // Init event loop.
        $this->eventLoop = new SupervisorDriver();
        EventLoop::setDriver($this->eventLoop);
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = $this->eventLoop->getSuspension();

        [$masterPipe, $this->workerPipeResource] = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->pipe = new InterprocessPipe($masterPipe);
        $this->serverStatus = new ServerStatus($this->workerPool->getWorkers(), true);
        $this->serverStatus->subscribeToWorkerMessages($this->pipe);

        $stopCallback = fn() => $this->stop();
        foreach ([SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT] as $signo) {
            $this->eventLoop->onSignal($signo, $stopCallback);
        }
        $this->eventLoop->onSignal(SIGUSR1, fn() => $this->requestServerStatus());
        $this->eventLoop->onSignal(SIGUSR2, fn() => $this->reload());
        $this->eventLoop->onChildProcessExit($this->onChildStop(...));
        $this->eventLoop->repeat(WorkerProcess::HEARTBEAT_PERIOD, fn() => $this->monitorWorkerStatus());

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
    private function doDaemonize(): bool
    {
        $pid = \pcntl_fork();
        if ($pid === -1) {
            throw new PHPStreamServerException('Fork fail');
        }
        if ($pid > 0) {
            return true;
        }
        if (\posix_setsid() === -1) {
            throw new PHPStreamServerException('Setsid fail');
        }
        return false;
    }

    private function saveMasterPid(): void
    {
        if (false === \file_put_contents($this->pidFile, (string) \posix_getpid())) {
            throw new PHPStreamServerException(\sprintf('Can\'t save pid to %s', $this->pidFile));
        }

        if(!\file_exists($this->pipeFile)) {
            \posix_mkfifo($this->pipeFile, 0644);
        }
    }

    private function spawnWorkers(): void
    {
        $this->eventLoop->defer(function (): void {
            foreach ($this->workerPool->getWorkers() as $worker) {
                while (\iterator_count($this->workerPool->getAliveWorkerPids($worker)) < $worker->getCount()) {
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
            $this->workerPool->addChild($worker, $pid);
            return false;
        } elseif ($pid === 0) {
            // Child process
            $this->suspension->resume($worker);
            return true;
        } else {
            throw new PHPStreamServerException('fork fail');
        }
    }

    // Runs in forked process
    private function runWorker(WorkerProcess $worker): int
    {
        $this->pipe->free();
        $this->eventLoop->queue(fn() => $this->eventLoop->stop());
        $this->eventLoop->run();
        unset($this->suspension, $this->pipe, $this->serverStatus, $this->workerPool);
        \gc_collect_cycles();
        \gc_mem_caches();

        return $worker->run($this->logger, $this->workerPipeResource);
    }

    private function monitorWorkerStatus(): void
    {
        foreach ($this->serverStatus->getProcesses() as $process) {
            $blockTime = $process->detached ? 0 : (int) \round((\hrtime(true) - $process->time) / 1000000000);
            if ($process->blocked === false && $blockTime > $this->serverStatus::BLOCK_WARNING_TRESHOLD) {
                $this->serverStatus->markProcessAsBlocked($process->pid);
                $this->logger->warning(\sprintf(
                    'Worker %s[pid:%d] blocked event loop for more than %s seconds',
                    $this->workerPool->getWorkerByPid($process->pid)->getName(),
                    $process->pid,
                    $blockTime,
                ));
            }
        }
    }

    private function onChildStop(int $pid, int $exitCode): void
    {
        $worker = $this->workerPool->getWorkerByPid($pid);
        $this->workerPool->deleteChild($pid);
        $this->serverStatus->deleteProcess($pid);

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
                if ($this->workerPool->getProcessesCount() === 0) {
                    // All processes are stopped now
                    $this->doStopProcess();
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

        // Send SIGTERM signal to all child processes
        foreach ($this->workerPool->getAlivePids() as $pid) {
            \posix_kill($pid, SIGTERM);
        }

        if ($this->workerPool->getWorkerCount() === 0) {
            $this->doStopProcess();
        } else {
            $this->eventLoop->delay($this->stopTimeout, function (): void {
                // Send SIGKILL signal to all child processes ater timeout
                foreach ($this->workerPool->getAlivePids() as $pid) {
                    \posix_kill($pid, SIGKILL);
                    $worker = $this->workerPool->getWorkerByPid($pid);
                    $this->logger->notice(\sprintf('Worker %s[pid:%s] killed after %ss timeout', $worker->getName(), $pid, $this->stopTimeout));
                }
                $this->doStopProcess();
            });
        }
    }

    private function doStopProcess(): void
    {
        $this->logger->info(Server::NAME . ' stopped');
        $this->suspension->resume();
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

        foreach ($this->workerPool->getAlivePids() as $pid) {
            \posix_kill($pid, $this->serverStatus->isDetached($pid) ? SIGTERM : SIGUSR2);
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

    private function requestServerStatus(): void
    {
        // rewrite async
        $fd = \fopen($this->pipeFile, 'w');
        \fwrite($fd, serialize($this->serverStatus));
        \fclose($fd);
    }

    public function getServerStatus(): ServerStatus
    {
        if ($this->isRunning() && \file_exists($this->pipeFile)) {
            \posix_kill($this->getPid(), SIGUSR1);
            $fd = \fopen($this->pipeFile, 'r');
            \stream_set_blocking($fd, true);
            /** @var ServerStatus $status */
            $status = \unserialize((string) \stream_get_contents($fd));
        } else {
            return new ServerStatus($this->workerPool->getWorkers(), false);
        }

        return $status;
    }
}
