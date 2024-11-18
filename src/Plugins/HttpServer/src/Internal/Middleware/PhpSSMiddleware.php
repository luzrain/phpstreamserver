<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\BundledPlugin\HttpServer\Internal\HttpErrorHandler;
use Luzrain\PHPStreamServer\Plugin\System\Connections\NetworkTrafficCounter;
use Luzrain\PHPStreamServer\Server;

/**
 * @internal
 */
final readonly class PhpSSMiddleware implements Middleware
{
    public function __construct(
        private HttpErrorHandler $errorHandler,
        private NetworkTrafficCounter $networkTrafficCounter,
        private \Closure $reloadStrategyTrigger,
    ) {
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        \memory_reset_peak_usage();

        try {
            $response = $requestHandler->handleRequest($request);
        } catch (\Throwable $exception) {
            $response = $this->errorHandler->handleException($exception, $request);
            ($this->reloadStrategyTrigger)($exception);
        }

        $this->networkTrafficCounter->incRequests();
        ($this->reloadStrategyTrigger)($request);

        if (!$response->hasHeader('server')) {
            $response->setHeader('server', Server::getVersionString());
        }

        return $response;
    }
}
