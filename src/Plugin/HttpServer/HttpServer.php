<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\WorkerProcess;

final class HttpServer extends Plugin
{
    private mixed $context = null;

    /**
     * @param Listen|string|array<Listen> $listen
     * @param \Closure(WorkerProcess, mixed): void $onStart
     * @param \Closure(Request, mixed): Response $onRequest
     */
    public function __construct(
        private Listen|string|array $listen,
        private \Closure $onStart,
        private \Closure $onRequest,
        private string $name = 'HTTP Server',
        private int $count = 1,
        private bool $reloadable = true,
        private string|null $user = null,
        private string|null $group = null,
        private array $middleware = [],
        private int|null $connectionLimit = null,
        private int|null $connectionLimitPerIp = null,
        private int|null $concurrencyLimit = null,
        private bool $http2Enabled = true,
        private int $connectionTimeout = HttpDriver::DEFAULT_CONNECTION_TIMEOUT,
        private int $headerSizeLimit = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT,
        private int $bodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT,
    ) {
    }

    public function init(MasterProcess $masterProcess): void
    {
        $masterProcess->addWorker(new WorkerProcess(
            name: $this->name,
            count: $this->count,
            reloadable: $this->reloadable,
            user: $this->user,
            group: $this->group,
            onStart: $this->onWorkerStart(...),
        ));
    }

    private function onWorkerStart(WorkerProcess $worker): void
    {
        ($this->onStart)($worker, $this->context);

        $requestHandler = new class($this->onRequest, $this->context) implements RequestHandler {
            public function __construct(private readonly \Closure $handler, private mixed &$context)
            {
            }

            public function handleRequest(Request $request): Response
            {
                return ($this->handler)($request, $this->context);
            }
        };

        $module = new HttpServerModule(
            listen: self::normalizeListenList($this->listen),
            requestHandler: $requestHandler,
            middleware: $this->middleware,
            connectionLimit: $this->connectionLimit,
            connectionLimitPerIp: $this->connectionLimitPerIp,
            concurrencyLimit: $this->concurrencyLimit,
            http2Enabled: $this->http2Enabled,
            connectionTimeout: $this->connectionTimeout,
            headerSizeLimit: $this->headerSizeLimit,
            bodySizeLimit: $this->bodySizeLimit,
        );

        $module->start($worker);
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
