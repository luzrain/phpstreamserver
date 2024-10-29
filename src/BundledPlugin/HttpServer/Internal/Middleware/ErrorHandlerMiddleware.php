<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\HttpErrorHandler;

final readonly class ErrorHandlerMiddleware implements Middleware
{
    public function __construct(private HttpErrorHandler $errorHandler)
    {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            return $requestHandler->handleRequest($request);
        } catch (\Throwable $e) {
            return $this->errorHandler->handleException($e, $request);
        }
    }
}
