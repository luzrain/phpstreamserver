<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use PHPStreamServer\Core\MessageBus\MessageBusInterface;
use PHPStreamServer\Core\Worker\LoggerInterface;
use PHPStreamServer\Plugin\Logger\Internal\LogEntry;
use PHPStreamServer\Plugin\Logger\Internal\MasterLogger;
use PHPStreamServer\Plugin\Logger\Internal\WorkerLogger;
use PHPStreamServer\Core\Internal\Container;
use PHPStreamServer\Core\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Core\Plugin\Plugin;
use Revolt\EventLoop;

final class LoggerPlugin extends Plugin
{
    /**
     * @var array<HandlerInterface>
     */
    private array $handlers;

    public function __construct(HandlerInterface ...$handlers)
    {
        $this->handlers = $handlers;
    }

    public function onStart(): void
    {
        $masterLogger = new MasterLogger();

        $workerLoggerFactory = static function (Container $container) {
            return new WorkerLogger($container->getService(MessageBusInterface::class));
        };

        $this->masterContainer->setService(LoggerInterface::class, $masterLogger);
        $this->workerContainer->registerService(LoggerInterface::class, $workerLoggerFactory);

        $messageBusHandler = $this->masterContainer->getService(MessageHandlerInterface::class);

        foreach ($this->handlers as $loggerHandler) {
            $loggerHandler
                ->start()
                ->map(function () use ($masterLogger, $loggerHandler) {
                    $masterLogger->addHandler($loggerHandler);
                })
                ->catch(function (\Throwable $e) use ($masterLogger) {
                    $masterLogger->error($e->getMessage(), ['exception' => $e]);
                })
            ;
        }

        $messageBusHandler->subscribe(LogEntry::class, static function (LogEntry $event) use ($masterLogger): void {
            EventLoop::queue(static function () use ($event, $masterLogger) {
                $masterLogger->logEntry($event);
            });
        });
    }
}
