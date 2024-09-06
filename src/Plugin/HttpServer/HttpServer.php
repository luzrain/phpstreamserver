<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Future;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\PluginInterface;
use Luzrain\PHPStreamServer\WorkerProcess;
use function Amp\async;

final readonly class HttpServer implements PluginInterface
{
    public function __construct(
        private Listen|array $listen,
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

    public function start(MasterProcess $masterProcess): void
    {
        $masterProcess->addWorker(new WorkerProcess(
            name: $this->name,
            count: $this->count,
            reloadable: $this->reloadable,
            user: $this->user,
            group: $this->group,
            onStart: function (WorkerProcess $worker) {
                $requestHandler = new ClosureRequestHandler(function (Request $request) : Response {
                    return match ($request->getUri()->getPath()) {
                        '/' => new Response(body: 'Hello world1'),
                        '/ping' => new Response(body: 'pong2'),
                        default => throw new HttpErrorException(404),
                    };
                });

                $worker->startWorkerModule(new HttpServerModule(
                    listen: $this->listen,
                    requestHandler: $requestHandler,
                    middleware: $this->middleware,
                    connectionLimit: $this->connectionLimit,
                    connectionLimitPerIp: $this->connectionLimitPerIp,
                    concurrencyLimit: $this->concurrencyLimit,
                    http2Enabled: $this->http2Enabled,
                    connectionTimeout: $this->connectionTimeout,
                    headerSizeLimit: $this->headerSizeLimit,
                    bodySizeLimit: $this->bodySizeLimit,
                ));
            },
        ));
    }

    public function stop(): Future
    {
        return async(static fn() => null);
    }
}
