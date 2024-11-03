<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\MasterLogger;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\WorkerLogger;
use Luzrain\PHPStreamServer\Internal\Container;
use Luzrain\PHPStreamServer\LoggerInterface;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Luzrain\PHPStreamServer\Plugin;
use Revolt\EventLoop;

final class LoggerPlugin extends Plugin
{
    public function __construct()
    {
    }

    public function init(): void
    {
        $masterLoggerFactory = static function () {
            return new MasterLogger();
        };

        $workerLoggerFactory = static function (Container $container) {
            return new WorkerLogger($container->get('bus'));
        };

        $this->masterContainer->register('logger', $masterLoggerFactory);
        $this->workerContainer->register('logger', $workerLoggerFactory);
    }

    public function start(): void
    {
        /** @var LoggerInterface $logger */
        $logger = $this->masterContainer->get('logger');

        /** @var MessageHandlerInterface $handler */
        $handler = $this->masterContainer->get('handler');

        $handler->subscribe(LogEntry::class, static function (LogEntry $event) use ($logger): void {
            EventLoop::queue(static function () use ($event, $logger) {
                $logger->logEntry($event);
            });
        });
    }
}
