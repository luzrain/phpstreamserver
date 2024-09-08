<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Future;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\RequestHandler;
use Luzrain\PHPStreamServer\Internal\MasterProcess;
use Luzrain\PHPStreamServer\Plugin\PluginInterface;
use Luzrain\PHPStreamServer\WorkerProcess;
use function Amp\async;

final readonly class HttpServer implements PluginInterface
{
    /**
     * @param Listen|string|array<Listen> $listen
     * @param \Closure(WorkerProcess): RequestHandler $onStart
     */
    public function __construct(
        private Listen|string|array $listen,
        private \Closure $onStart,
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
                $requestHandler = ($this->onStart)($worker);

                if (!$requestHandler instanceof RequestHandler) {
                    throw new \RuntimeException(sprintf(
                        'onStart() closure: Return value must be of type %s, %s returned',
                        RequestHandler::class,
                        \get_debug_type($requestHandler)),
                    );
                }

                $worker->startWorkerModule(new HttpServerModule(
                    listen: self::createListenList($this->listen),
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

    /**
     * @return list<Listen>
     */
    public static function createListenList(self|string|array $listen): array
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
