<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer;

use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\Middleware\CompressionMiddleware;
use PHPStreamServer\Core\Plugin\Plugin;

final class HttpServerPlugin extends Plugin
{
    public function __construct(
        private readonly bool $http2Enable = true,
        private readonly int $httpConnectionTimeout = HttpDriver::DEFAULT_CONNECTION_TIMEOUT,
        private readonly int $httpHeaderSizeLimit = HttpDriver::DEFAULT_HEADER_SIZE_LIMIT,
        private readonly int $httpBodySizeLimit = HttpDriver::DEFAULT_BODY_SIZE_LIMIT,
        private readonly int $gzipMinLength = CompressionMiddleware::DEFAULT_MINIMUM_LENGTH,
        private readonly string $gzipTypesRegex = CompressionMiddleware::DEFAULT_CONTENT_TYPE_REGEX,
    ) {
    }

    public function onStart(): void
    {
        $this->workerContainer->setParameter('httpServerPlugin.http2Enable', $this->http2Enable);
        $this->workerContainer->setParameter('httpServerPlugin.httpConnectionTimeout', $this->httpConnectionTimeout);
        $this->workerContainer->setParameter('httpServerPlugin.httpHeaderSizeLimit', $this->httpHeaderSizeLimit);
        $this->workerContainer->setParameter('httpServerPlugin.httpBodySizeLimit', $this->httpBodySizeLimit);
        $this->workerContainer->setParameter('httpServerPlugin.gzipMinLength', $this->gzipMinLength);
        $this->workerContainer->setParameter('httpServerPlugin.gzipTypesRegex', $this->gzipTypesRegex);
    }
}
