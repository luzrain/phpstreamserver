<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Middleware\CompressionMiddleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;

final readonly class GzipMiddleware implements Middleware
{
    private CompressionMiddleware $inner;

    public function __construct(
        int $minimumLength = CompressionMiddleware::DEFAULT_MINIMUM_LENGTH,
        string $contentRegex = CompressionMiddleware::DEFAULT_CONTENT_TYPE_REGEX,
        float $bufferTimeout = CompressionMiddleware::DEFAULT_BUFFER_TIMEOUT,
    ) {
        $this->inner = new CompressionMiddleware($minimumLength, $contentRegex, $bufferTimeout);
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        return $this->inner->handleRequest($request, $requestHandler);
    }
}
