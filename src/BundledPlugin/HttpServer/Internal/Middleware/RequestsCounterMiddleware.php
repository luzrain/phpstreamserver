<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Internal\ConnectionStatus\NetworkTrafficCounter;

final class RequestsCounterMiddleware implements Middleware
{
    public function __construct(private readonly NetworkTrafficCounter $trafficStatisticStore)
    {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $this->trafficStatisticStore->incRequests();

        return $requestHandler->handleRequest($request);
    }
}