<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer;

use Amp\Http\Server\Driver\HttpDriver;
use Luzrain\PHPStreamServer\MasterProcessIntarface;
use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\Process;

final class HttpServerPlugin extends Plugin
{
    public function __construct(
        private readonly bool $http2Enabled = true,
        private readonly int $connectionTimeout = HttpDriver::DEFAULT_CONNECTION_TIMEOUT,
        private readonly int $headerSizeLimit = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT,
        private readonly int $bodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT,
    ) {
    }

    public function init(MasterProcessIntarface $masterProcess): void
    {
        $workerContainer = $masterProcess->getWorkerContainer();
        $workerContainer->set('httpServerPlugin.http2Enabled', $this->http2Enabled);
        $workerContainer->set('httpServerPlugin.connectionTimeout', $this->connectionTimeout);
        $workerContainer->set('httpServerPlugin.headerSizeLimit', $this->headerSizeLimit);
        $workerContainer->set('httpServerPlugin.bodySizeLimit', $this->bodySizeLimit);
    }

    public function addWorker(Process $worker): void
    {
        \assert($worker instanceof HttpServerProcess);
    }
}
