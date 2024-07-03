<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Server;

final class AddServerHeadersMiddleware implements Middleware
{
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $response = $requestHandler->handleRequest($request);
        $response->setHeader('server', Server::VERSION_STRING);

        return $response;
    }
}
