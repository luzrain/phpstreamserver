<?php

declare(strict_types=1);

namespace PHPStreamServer\MetricsPlugin;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\ResourceServerSocketFactory;
use PHPStreamServer\BundledPlugin\HttpServer\Internal\HttpErrorHandler;
use PHPStreamServer\BundledPlugin\HttpServer\Internal\HttpServer;
use PHPStreamServer\BundledPlugin\HttpServer\Listen;
use PHPStreamServer\MetricsPlugin\Internal\MessageBusRegistry;
use PHPStreamServer\MetricsPlugin\Internal\MessageBusRegistryHandler;
use PHPStreamServer\MetricsPlugin\Internal\NotFoundPage;
use PHPStreamServer\Internal\Container;
use PHPStreamServer\Plugin;
use PHPStreamServer\Worker\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

final class MetricsPlugin extends Plugin
{
    public function __construct(
        private readonly Listen|string $listen,
    ) {
    }

    public function onStart(): void
    {
        $listen = \is_string($this->listen) ? new Listen($this->listen) : $this->listen;

        $this->masterContainer->set(RegistryInterface::class, new MessageBusRegistry($this->masterContainer->get('bus')));

        $this->workerContainer->register(RegistryInterface::class, static function (Container $container): RegistryInterface {
            return new MessageBusRegistry($container->get('bus'));
        });

        /** @var LoggerInterface $logger */
        $logger = &$this->masterContainer->get('logger');
        $handler = &$this->masterContainer->get('handler');

        $nullLogger = new NullLogger();
        $serverSocketFactory = new ResourceServerSocketFactory();
        $clientFactory = new SocketClientFactory($nullLogger);
        $errorHandler = new HttpErrorHandler($nullLogger);
        $socketHttpServer = new SocketHttpServer(
            logger: $nullLogger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            allowedMethods: ['GET'],
            httpDriverFactory: new DefaultHttpDriverFactory(logger: $nullLogger),
        );

        $socketHttpServer->expose(...HttpServer::createInternetAddressAndContext($listen));

        $messageBusRegistryHandler = new MessageBusRegistryHandler($handler);

        EventLoop::defer(function () use ($logger, $socketHttpServer, $messageBusRegistryHandler, $errorHandler, $listen) {
            $requestHandler = $this->createRequestHandler($messageBusRegistryHandler);
            $socketHttpServer->start($requestHandler, $errorHandler);
            $logger->info(\sprintf('Prometheus metrics available on %s/metrics', $listen->getAddress()));
        });
    }

    private function createRequestHandler(MessageBusRegistryHandler $messageBusRegistryHandler): RequestHandler
    {
        return new class($messageBusRegistryHandler) implements RequestHandler
        {
            public function __construct(private readonly MessageBusRegistryHandler $messageBusRegistryHandler)
            {
            }

            public function handleRequest(Request $request): Response
            {
                if ($request->getUri()->getPath() !== '/metrics') {
                    return new Response(body: (new NotFoundPage())->toHtml(), status: HttpStatus::NOT_FOUND);
                }

                $result = $this->messageBusRegistryHandler->render();
                $headers = ['content-type' => 'text/plain; version=0.0.4'];

                return new Response(body: $result, headers: $headers);
            }
        };
    }
}
