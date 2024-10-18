<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Logger;

use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\Internal\Logger\LoggerInterface;
use Luzrain\PHPStreamServer\Internal\MessageBus\MessageBus;
use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\Plugin\Logger\Internal\MasterLogger;
use Luzrain\PHPStreamServer\Plugin\Logger\Internal\WorkerLogger;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Revolt\EventLoop;

final class Logger extends Plugin
{
    private MasterProcess $masterProcess;

    public function __construct()
    {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $this->masterProcess = $masterProcess;

        $masterLoggerFactory = static function () {
            return new MasterLogger();
        };

        $workerLoggerFactory = function (Container $container) {
            return new WorkerLogger($container->get('bus'));
        };

        $this->masterProcess->masterContainer->register('logger', $masterLoggerFactory);
        $this->masterProcess->workerContainer->register('logger', $workerLoggerFactory);
    }

    public function start(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->masterProcess->masterContainer->get('logger');

        /** @var MessageBus $bus */
        $bus = $this->masterProcess->masterContainer->get('bus');

        $bus->subscribe(LogEntry::class, static function (LogEntry $event) use ($logger): void {
            EventLoop::queue(static function () use ($event, $logger) {
                $logger->logEntry($event);
            });
        });
    }
}
