<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer;

use Amp\Http\Server\Driver\HttpDriver;
use Luzrain\PHPStreamServer\Plugin;

final class HttpServerPlugin extends Plugin
{
    public function __construct(
        private readonly bool $http2Enabled = true,
        private readonly int $connectionTimeout = HttpDriver::DEFAULT_CONNECTION_TIMEOUT,
        private readonly int $headerSizeLimit = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT,
        private readonly int $bodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT,
    ) {
    }

    public function init(): void
    {
        $this->workerContainer->set('httpServerPlugin.http2Enabled', $this->http2Enabled);
        $this->workerContainer->set('httpServerPlugin.connectionTimeout', $this->connectionTimeout);
        $this->workerContainer->set('httpServerPlugin.headerSizeLimit', $this->headerSizeLimit);
        $this->workerContainer->set('httpServerPlugin.bodySizeLimit', $this->bodySizeLimit);
    }
}
