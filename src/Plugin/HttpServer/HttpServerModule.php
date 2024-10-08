<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Http\Server\Driver\ConnectionLimitingServerSocketFactory;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\ConcurrencyLimitingMiddleware;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerTlsContext;
use Amp\Sync\LocalSemaphore;
use Luzrain\PHPStreamServer\Internal\ReloadStrategy\ReloadStrategyAwareInterface;
use Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus\NetworkTrafficCounterAwareInterface;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\HttpClientFactory;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\HttpErrorHandler;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\AddServerHeadersMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\ClientExceptionHandleMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\ReloadStrategyTriggerMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\RequestsCounterMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\TrafficCountingClientFactory;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\TrafficCountingSocketFactory;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Middleware\StaticMiddleware;
use Luzrain\PHPStreamServer\WorkerProcessInterface;

final readonly class HttpServerModule
{
    private const DEFAULT_TCP_BACKLOG = 65536;

    /**
     * @param Listen|array<Listen> $listen
     * @param array<Middleware> $middleware
     * @param positive-int|null $connectionLimit
     * @param positive-int|null $connectionLimitPerIp
     * @param positive-int|null $concurrencyLimit
     */
    public function __construct(
        private Listen|array $listen,
        private RequestHandler $requestHandler,
        private array $middleware = [],
        private int|null $connectionLimit = null,
        private int|null $connectionLimitPerIp = null,
        private int|null $concurrencyLimit = null,
        private bool $http2Enabled = true,
        private int $connectionTimeout = HttpDriver::DEFAULT_CONNECTION_TIMEOUT,
        private int $headerSizeLimit = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT,
        private int $bodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT,
        private \Closure|null $onConnect = null,
        private \Closure|null $onClose = null,
    ) {
    }

    public function start(WorkerProcessInterface $worker): void
    {
        $middleware = [];

        $serverSocketFactory = new ResourceServerSocketFactory();

        $clientFactory = new HttpClientFactory(
            logger: $worker->getLogger(),
            connectionLimitPerIp: $this->connectionLimitPerIp,
            onConnectCallback: $this->onConnect,
            onCloseCallback: $this->onClose,
        );

        if ($this->connectionLimit !== null) {
            $serverSocketFactory = new ConnectionLimitingServerSocketFactory(new LocalSemaphore($this->connectionLimit), $serverSocketFactory);
        }

        if ($worker instanceof NetworkTrafficCounterAwareInterface) {
            $networkTrafficCounter = $worker->getNetworkTrafficCounter();
            $serverSocketFactory = new TrafficCountingSocketFactory($serverSocketFactory, $networkTrafficCounter);
            $clientFactory = new TrafficCountingClientFactory($clientFactory, $networkTrafficCounter);
            $middleware[] = new RequestsCounterMiddleware($networkTrafficCounter);
        }

        if ($this->concurrencyLimit !== null) {
            $middleware[] = new ConcurrencyLimitingMiddleware($this->concurrencyLimit);
        }

        if ($worker instanceof ReloadStrategyAwareInterface) {
            $middleware[] = new ReloadStrategyTriggerMiddleware($worker);
        }

        $middleware[] = new ClientExceptionHandleMiddleware();
        $middleware[] = new AddServerHeadersMiddleware();

        \array_push($middleware, ...$this->middleware);

        // Force move StaticMiddleware to the end of the chain
        \usort($middleware, static fn (mixed $a): int => $a instanceof StaticMiddleware ? 1 : -1);

        $socketHttpServer = new SocketHttpServer(
            logger: $worker->getLogger(),
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            middleware: $middleware,
            allowedMethods: null,
            httpDriverFactory: new DefaultHttpDriverFactory(
                logger: $worker->getLogger(),
                streamTimeout: $this->connectionTimeout,
                connectionTimeout: $this->connectionTimeout,
                headerSizeLimit: $this->headerSizeLimit,
                bodySizeLimit: $this->bodySizeLimit,
                http2Enabled: $this->http2Enabled,
                pushEnabled: true,
            ),
        );

        foreach (\is_array($this->listen) ? $this->listen : [$this->listen] as $listen) {
            $socketHttpServer->expose(...$this->createInternetAddressAndContext($listen));
        }

        $socketHttpServer->start($this->requestHandler, new HttpErrorHandler());
    }

    /**
     * @return array{0: InternetAddress, 1: BindContext}
     */
    private function createInternetAddressAndContext(Listen $listen): array
    {
        $internetAddress = new InternetAddress($listen->host, $listen->port);

        $context = (new BindContext())
            ->withReusePort()
            ->withBacklog(self::DEFAULT_TCP_BACKLOG)
        ;

        if ($listen->tls) {
            \assert($listen->tlsCertificate !== null);
            $context = $context->withTlsContext(
                (new ServerTlsContext())->withDefaultCertificate(new Certificate($listen->tlsCertificate, $listen->tlsCertificateKey)),
            );
        }

        return [$internetAddress, $context];
    }
}
