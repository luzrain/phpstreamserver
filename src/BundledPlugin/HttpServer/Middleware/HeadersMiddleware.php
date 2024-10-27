<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;

final readonly class HeadersMiddleware implements Middleware
{
    public function __construct(
        /**
         * @var array<string, string|array<string>>
         */
        private array $headers,
    ) {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $response = $requestHandler->handleRequest($request);
        $response->replaceHeaders($this->headers);

        return $response;
    }
}
