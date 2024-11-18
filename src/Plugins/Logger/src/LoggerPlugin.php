<?php

declare(strict_types=1);

namespace PHPStreamServer\LoggerPlugin;

use PHPStreamServer\LoggerPlugin\Internal\LogEntry;
use PHPStreamServer\LoggerPlugin\Internal\MasterLogger;
use PHPStreamServer\LoggerPlugin\Internal\WorkerLogger;
use PHPStreamServer\Internal\Container;
use PHPStreamServer\MessageBus\MessageHandlerInterface;
use PHPStreamServer\Plugin;
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
        $logger = new MasterLogger();

        $workerLoggerFactory = static function (Container $container) {
            return new WorkerLogger($container->get('bus'));
        };

        $this->masterContainer->set('logger', $logger);
        $this->workerContainer->register('logger', $workerLoggerFactory);

        /** @var MessageHandlerInterface $messageBusHandler */
        $messageBusHandler = $this->masterContainer->get('handler');

        foreach ($this->handlers as $loggerHandler) {
            $loggerHandler
                ->start()
                ->map(function () use ($logger, $loggerHandler) {
                    $logger->addHandler($loggerHandler);
                })
                ->catch(function (\Throwable $e) use ($logger) {
                    $logger->error($e->getMessage(), ['exception' => $e]);
                })
            ;
        }

        $messageBusHandler->subscribe(LogEntry::class, static function (LogEntry $event) use ($logger): void {
            EventLoop::queue(static function () use ($event, $logger) {
                $logger->logEntry($event);
            });
        });
    }
}
