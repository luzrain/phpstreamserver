<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\HttpServer\Internal\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use PHPStreamServer\Core\Server;
use PHPStreamServer\Plugin\Metrics\Counter;
use PHPStreamServer\Plugin\Metrics\Histogram;
use PHPStreamServer\Plugin\Metrics\RegistryInterface;

/**
 * @internal
 */
final readonly class MetricsMiddleware implements Middleware
{
    private Counter $requestCounter;
    private Histogram $requestDuration;

    public function __construct(RegistryInterface $registry)
    {
        $this->requestCounter = $registry->registerCounter(
            namespace: Server::SHORTNAME,
            name: 'http_requests_total',
            help: 'Total number of handled HTTP requests',
            labels: ['code'],
        );

        $this->requestDuration = $registry->registerHistogram(
            namespace: Server::SHORTNAME,
            name: 'http_duration_seconds',
            help: 'HTTP request duration',
            buckets: [0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],
        );
    }

    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        $tsStart = \hrtime(true);
        $response = $requestHandler->handleRequest($request);
        $handleTimeSeconds = (\hrtime(true) - $tsStart) * 1e-9;
        $statusCode = $response->getStatus();

        $this->requestCounter->inc(['code' => $statusCode]);
        $this->requestDuration->observe($handleTimeSeconds);

        return $response;
    }
}
