<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal;

use Amp\Http\Server\Driver\ConnectionLimitingClientFactory;
use Amp\Http\Server\Driver\ConnectionLimitingServerSocketFactory;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerTlsContext;
use Amp\Sync\LocalSemaphore;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\Middleware\AccessLoggerMiddleware;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\Middleware\PhpSSMiddleware;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\Middleware\StaticMiddleware;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Listen;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\NetworkTrafficCounter;
use Luzrain\PHPStreamServer\Worker\LoggerInterface;

/**
 * @internal
 */
final readonly class HttpServer
{
    private const DEFAULT_TCP_BACKLOG = 65536;

    /**
     * @param array<Listen> $listen
     * @param array<Middleware> $middleware
     */
    public function __construct(
        private array $listen,
        private RequestHandler $requestHandler,
        private array $middleware,
        private int|null $connectionLimit,
        private int|null $connectionLimitPerIp,
        private int|null $concurrencyLimit,
        private bool $http2Enabled,
        private int $connectionTimeout,
        private int $headerSizeLimit,
        private int $bodySizeLimit,
        private LoggerInterface $logger,
        private NetworkTrafficCounter $networkTrafficCounter,
        private \Closure $reloadStrategyTrigger,
        private bool $accessLog,
        private string|null $serveDir,
    ) {
    }

    public function start(): void
    {
        $middleware = [];
        $errorHandler = new HttpErrorHandler($this->logger);
        $serverSocketFactory = new ResourceServerSocketFactory();
        $clientFactory = new SocketClientFactory($this->logger);

        if ($this->connectionLimitPerIp !== null) {
            $clientFactory = new ConnectionLimitingClientFactory($clientFactory, $this->logger, $this->connectionLimitPerIp);
        }

        if ($this->connectionLimit !== null) {
            $serverSocketFactory = new ConnectionLimitingServerSocketFactory(new LocalSemaphore($this->connectionLimit), $serverSocketFactory);
        }

        $serverSocketFactory = new TrafficCountingSocketFactory($serverSocketFactory, $this->networkTrafficCounter);

        if ($this->concurrencyLimit !== null) {
            $middleware[] = new Middleware\ConcurrencyLimitingMiddleware($this->concurrencyLimit);
        }

        if ($this->accessLog) {
            $middleware[] = new AccessLoggerMiddleware($this->logger->withChannel('http'));
        }

        $middleware = [...$middleware, ...$this->middleware];

        $middleware[] = new PhpSSMiddleware($errorHandler, $this->networkTrafficCounter, $this->reloadStrategyTrigger);

        // StaticMiddleware must be at the end of the chain.
        if ($this->serveDir !== null) {
            $middleware[] = new StaticMiddleware($this->serveDir);
        }

        $socketHttpServer = new SocketHttpServer(
            logger: $this->logger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            middleware: $middleware,
            allowedMethods: null,
            httpDriverFactory: new DefaultHttpDriverFactory(
                logger: $this->logger,
                streamTimeout: $this->connectionTimeout,
                connectionTimeout: $this->connectionTimeout,
                headerSizeLimit: $this->headerSizeLimit,
                bodySizeLimit: $this->bodySizeLimit,
                http2Enabled: $this->http2Enabled,
                pushEnabled: true,
            ),
        );

        foreach ($this->listen as $listen) {
            $socketHttpServer->expose(...self::createInternetAddressAndContext($listen, true, self::DEFAULT_TCP_BACKLOG));
        }

        $socketHttpServer->start($this->requestHandler, $errorHandler);
    }

    /**
     * @return array{0: InternetAddress, 1: BindContext}
     */
    public static function createInternetAddressAndContext(Listen $listen, bool $reusePort = false, int $backlog = 0): array
    {
        $internetAddress = new InternetAddress($listen->host, $listen->port);
        $context = new BindContext();

        if ($reusePort) {
            $context = $context->withReusePort();
        }

        if ($backlog > 0) {
            $context = $context->withBacklog($backlog);
        }

        if ($listen->tls) {
            \assert($listen->tlsCertificate !== null);
            $cert = new Certificate($listen->tlsCertificate, $listen->tlsCertificateKey);
            $tlsContext = (new ServerTlsContext())->withDefaultCertificate($cert);
            $context = $context->withTlsContext($tlsContext);
        }

        return [$internetAddress, $context];
    }
}
