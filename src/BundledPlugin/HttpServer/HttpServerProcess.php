<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer;

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\AmpHttpServer;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\WorkerProcess;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\NetworkTrafficCounter;

final class HttpServerProcess extends WorkerProcess
{
    private mixed $context = null;

    /**
     * @param Listen|string|array<Listen> $listen
     * @param null|\Closure(self, mixed):void $onStart
     * @param null|\Closure(self):void $onStop
     * @param null|\Closure(self):void $onReload
     * @param \Closure(Request, mixed): Response $onRequest
     */
    public function __construct(
        private Listen|string|array $listen,
        private \Closure $onRequest,
        string $name = 'HTTP Server',
        int $count = 1,
        bool $reloadable = true,
        string|null $user = null,
        string|null $group = null,
        private \Closure|null $onStart = null,
        \Closure|null $onStop = null,
        \Closure|null $onReload = null,
        private array $middleware = [],
        private int|null $connectionLimit = null,
        private int|null $connectionLimitPerIp = null,
        private int|null $concurrencyLimit = null,
    ) {
        parent::__construct(
            name: $name,
            count: $count,
            reloadable: $reloadable,
            user: $user,
            group: $group,
            onStart: $this->onStart(...),
            onStop: $onStop,
            onReload: $onReload,
        );
    }

    static public function handleBy(): array
    {
        return [...parent::handleBy(), HttpServerPlugin::class];
    }

    private function onStart(): void
    {
        if ($this->onStart !== null) {
            ($this->onStart)($this, $this->context);
        }

        $requestHandler = new class($this->onRequest, $this->context) implements RequestHandler {
            public function __construct(private readonly \Closure $handler, private mixed &$context)
            {
            }

            public function handleRequest(Request $request): Response
            {
                return ($this->handler)($request, $this->context);
            }
        };

        $httpServer = new AmpHttpServer(
            listen: self::normalizeListenList($this->listen),
            requestHandler: $requestHandler,
            middleware: $this->middleware,
            connectionLimit: $this->connectionLimit,
            connectionLimitPerIp: $this->connectionLimitPerIp,
            concurrencyLimit: $this->concurrencyLimit,
            http2Enabled: $this->container->get('httpServerPlugin.http2Enabled'),
            connectionTimeout: $this->container->get('httpServerPlugin.connectionTimeout'),
            headerSizeLimit: $this->container->get('httpServerPlugin.headerSizeLimit'),
            bodySizeLimit: $this->container->get('httpServerPlugin.bodySizeLimit'),
        );

        $networkTrafficCounter = new NetworkTrafficCounter($this->container->get('bus'));

        $httpServer->start($this->logger, $networkTrafficCounter, $this);
    }

    /**
     * @return list<Listen>
     */
    private static function normalizeListenList(self|string|array $listen): array
    {
        $listen = \is_array($listen) ? $listen : [$listen];
        $ret = [];
        foreach ($listen as $listenItem) {
            if ($listenItem instanceof Listen) {
                $ret[] = $listenItem;
            } elseif (\is_string($listenItem)) {
                $ret[] = new Listen($listenItem);
            } else {
                throw new \InvalidArgumentException('Invalid listen');
            }
        }

        return $ret;
    }
}
