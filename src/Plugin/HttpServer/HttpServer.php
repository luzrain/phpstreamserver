<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\ConcurrencyLimitingMiddleware;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\InternetAddress;
use Amp\Socket\ServerTlsContext;
use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\HttpClientFactory;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\HttpErrorHandler;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\HttpServerSocketFactory;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\AddServerHeadersMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\ClientExceptionHandleMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\ReloadStrategyTriggerMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware\RequestsCounterMiddleware;
use Luzrain\PHPStreamServer\Plugin\HttpServer\Middleware\StaticMiddleware;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Psr\Log\LoggerInterface;

final readonly class HttpServer implements Plugin
{
    private const DEFAULT_TCP_BACKLOG = 65536;

    private string $scheme;
    private string $host;
    /** @var int<0, 65535> */
    private int $port;
    private bool $tls;

    public function __construct(
        string $listen,
        private RequestHandler $requestHandler,
        /** @var array<Middleware> $middleware */
        private array $middleware = [],
        private string|null $tlsCertificate = null,
        private string|null $tlsCertificateKey = null,
        /** @var positive-int|null */
        private int|null $connectionLimit = null,
        /** @var positive-int|null */
        private int|null $connectionLimitPerIp = null,
        /** @var positive-int|null */
        private int|null $concurrencyLimit = null,
        private bool $http2Enabled = true,
        private int $connectionTimeout = HttpDriver::DEFAULT_CONNECTION_TIMEOUT,
        private int $headerSizeLimit = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT,
        private int $bodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT,
        private \Closure|null $onConnect = null,
        private \Closure|null $onClose = null,
    ) {
        /** @var array{scheme: string, host: string|null, path: string|null, port: int<0, 65535>|null} $parts */
        $parts = \parse_url($listen);
        $this->scheme = $parts['scheme'] ?? 'http';
        $this->tls = $this->scheme === 'https';
        $this->host = $parts['host'] ?? $parts['path'] ?? '';
        $this->port = (int) ($parts['port'] ?? ($this->tls ? 443 : 80));

        if (!\in_array($this->scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid scheme. Should be either "http" or "https", "%s" given.', $this->scheme));
        }

        if ($this->tls && $this->tlsCertificate === null) {
            throw new \InvalidArgumentException('Certificate file must be provided');
        }
    }

    public function start(
        LoggerInterface $logger,
        TrafficStatus $trafficStatus,
        ReloadStrategyTrigger $reloadStrategyTrigger,
    ): void {
        $serverSocketFactory = new HttpServerSocketFactory($this->connectionLimit, $trafficStatus);
        $clientFactory = new HttpClientFactory($logger, $this->connectionLimitPerIp, $trafficStatus, $this->onConnect, $this->onClose);
        $middleware = [];

        if ($this->concurrencyLimit !== null) {
            $middleware[] = new ConcurrencyLimitingMiddleware($this->concurrencyLimit);
        }

        $middleware[] = new RequestsCounterMiddleware($trafficStatus);
        $middleware[] = new ClientExceptionHandleMiddleware();
        $middleware[] = new ReloadStrategyTriggerMiddleware($reloadStrategyTrigger);
        $middleware[] = new AddServerHeadersMiddleware();
        \array_push($middleware, ...$this->middleware);

        // Force move StaticMiddleware to the end of the chain
        \usort($middleware, fn (mixed $a): int => $a instanceof StaticMiddleware ? 1 : -1);

        $context = (new BindContext())
            ->withReusePort()
            ->withBacklog(self::DEFAULT_TCP_BACKLOG)
        ;

        if ($this->tls) {
            \assert($this->tlsCertificate !== null);
            $context = $context->withTlsContext(
                (new ServerTlsContext())->withDefaultCertificate(new Certificate($this->tlsCertificate, $this->tlsCertificateKey)),
            );
        }

        $socketHttpServer = new SocketHttpServer(
            logger: $logger,
            serverSocketFactory: $serverSocketFactory,
            clientFactory: $clientFactory,
            middleware: $middleware,
            allowedMethods: null,
            httpDriverFactory: new DefaultHttpDriverFactory(
                logger: $logger,
                streamTimeout: $this->connectionTimeout,
                connectionTimeout: $this->connectionTimeout,
                headerSizeLimit: $this->headerSizeLimit,
                bodySizeLimit: $this->bodySizeLimit,
                http2Enabled: $this->http2Enabled,
                pushEnabled: true,
            ),
        );

        $socketHttpServer->expose(new InternetAddress($this->host, $this->port), $context);
        $socketHttpServer->start($this->requestHandler, new HttpErrorHandler());
    }
}
