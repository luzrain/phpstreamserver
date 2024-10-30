<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Internal\ReloadStrategy\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\BundledPlugin\System\Connections\NetworkTrafficCounter;
use Luzrain\PHPStreamServer\Server;

final readonly class AmpHttpServerMiddleware implements Middleware
{
    public function __construct(
        private HttpErrorHandler $errorHandler,
        private NetworkTrafficCounter $networkTrafficCounter,
        private ReloadStrategyTrigger $reloadStrategyTrigger,
    ) {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            $response = $requestHandler->handleRequest($request);
        } catch (\Throwable $exception) {
            $response = $this->errorHandler->handleException($exception, $request);
            $this->reloadStrategyTrigger->emitEvent($exception);
        }

        $this->networkTrafficCounter->incRequests();
        $this->reloadStrategyTrigger->emitEvent($request);

        if (!$response->hasHeader('server')) {
            $response->setHeader('server', Server::VERSION_STRING);
        }

        return $response;
    }
}
