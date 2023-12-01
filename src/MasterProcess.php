<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner;

use Luzrain\PhpRunner\Exception\PHPRunnerException;
use Luzrain\PhpRunner\Internal\ErrorHandler;
use Luzrain\PhpRunner\Internal\Functions;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\DriverFactory;
use Revolt\EventLoop\Suspension;

final class MasterProcess
{
    private const STATUS_STARTING = 1;
    private const STATUS_RUNNING = 2;
    private const STATUS_SHUTDOWN = 3;
    private const STATUS_RELOADING = 4;

    private static bool $registered = false;
    private readonly string $startFile;
    private readonly string $pidFile;
    private readonly int $masterPid;
    private Driver $eventLoop;
    private Suspension $suspension;
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
        $this->pidFile = $this->config->pidFile ?? sprintf('%s/phprunner.%s.pid', sys_get_temp_dir(), hash('xxh64', $this->startFile));

        ErrorHandler::register($this->logger);
    }

    public function run(bool $isDaemon = false): never
    {
        if ($this->getStatus() === self::STATUS_RUNNING) {
            $this->logger->error('Master process already running');
            exit;
        }

        $this->initServer();
        $this->saveMasterPid();
        $this->initSignalHandler();
        $this->status = self::STATUS_RUNNING;
        $this->spawnWorkers();
        ([$worker, $parentSocket] = $this->suspension->suspend())
            ? exit($this->prepareWorker($worker, $parentSocket)->run())
            : exit($this->exitCode);
    }

    // Runs in master process
    private function initServer(): void
    {
        cli_set_process_title(sprintf('PHPRunner: master process  start_file=%s', $this->startFile));

        // Init event loop.
        // Force use StreamSelectDriver in the master process because it uses pcntl_signal to handle signals, and it works better for this case.
        $this->eventLoop = new StreamSelectDriver();
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));
        $this->suspension = $this->eventLoop->getSuspension();
    }

    private function saveMasterPid(): void
    {
        $this->masterPid = \posix_getpid();
        if (false === \file_put_contents($this->pidFile, $this->masterPid)) {
            throw new PHPRunnerException(\sprintf('Can\'t save pid to %s', $this->masterPid));
        }
    }

    private function initSignalHandler(): void
    {
        foreach ([SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT, SIGCHLD] as $signo) {
            $this->eventLoop->onSignal($signo, function (string $id, int $signo): void {
                match ($signo) {
                    SIGINT, SIGTERM, SIGHUP, SIGTSTP, SIGQUIT => $this->stop(),
                    SIGCHLD => $this->watchWorkers(),
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
            $callbackId = $this->eventLoop->onReadable($pair[1], function (string $id, mixed $resource) use ($worker, $pid) {
                $buffer = '';
                while (($c = \fgetc($resource) ?: "\0") !== "\0") {
                    $buffer .= $c;
                }
                if ($buffer !== '') {
                    $this->onMessageFromWorker($worker, $pid, \unserialize($buffer));
                }
            });
            $this->pool->addChild($worker, $pid, $callbackId);
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
            $callbackId = $this->pool->getSocketCallbackIdByPid($pid);
            $this->eventLoop->cancel($callbackId);
            $this->pool->deleteChild($pid);
            $this->onWorkerStop($worker, $pid, $exitCode);
        }
    }

    /**
     * Runs in forked process
     * @param resource $parentSocket
     */
    private function prepareWorker(WorkerProcess $worker, mixed $parentSocket): WorkerProcess
    {
        $this->eventLoop->stop();
        unset($this->suspension);
        unset($this->eventLoop);
        unset($this->pool);

        // Init new event loop for worker process
        $this->eventLoop = (new DriverFactory())->create();
        $this->eventLoop->setErrorHandler(ErrorHandler::handleException(...));

        \cli_set_process_title(sprintf('PHPRunner: worker process  %s', $worker->name));

        $worker->setDependencies(
            $this->eventLoop,
            $this->logger,
            $parentSocket,
        );

        return $worker;
    }

    private function onWorkerStop(WorkerProcess $worker, int $pid, int $exitCode): void
    {
        switch ($this->status) {
            case self::STATUS_SHUTDOWN:
                if (iterator_count($this->pool->getAlivePids()) === 0) {
                    // All workers are stopped now
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

    private function onMessageFromWorker(WorkerProcess $worker, int $pid, mixed $message): void
    {
        dump(sprintf("message from worker[%s]:", $pid));
        dump($message);
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

    public function getStatus(): int
    {
        $masterPid = \is_file($this->pidFile) ? (int)\file_get_contents($this->pidFile) : 0;
        $isRunning = !empty($masterPid) && \posix_getpid() !== $masterPid && \posix_kill($masterPid, 0);

        return $isRunning ? self::STATUS_RUNNING : $this->status;
    }
}
