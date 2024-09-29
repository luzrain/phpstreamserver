<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer\Internal\Middleware;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Internal\ReloadStrategy\ReloadStrategyAwareInterface;

final readonly class ReloadStrategyTriggerMiddleware implements Middleware
{
    public function __construct(private ReloadStrategyAwareInterface $worker)
    {
    }

    /**
     * @throws \Throwable
     */
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            return $requestHandler->handleRequest($request);
        } catch (\Throwable $e) {
            $this->worker->emitReloadEvent($e);
            throw $e;
        } finally {
            $this->worker->emitReloadEvent($request);
        }
    }
}
