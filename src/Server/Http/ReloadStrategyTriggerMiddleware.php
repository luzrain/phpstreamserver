<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http;

use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;

final readonly class ReloadStrategyTriggerMiddleware implements Middleware
{
    public function __construct(
        private ReloadStrategyTrigger $reloadStrategyTrigger,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            return $requestHandler->handleRequest($request);
        } catch (\Throwable $e) {
            $this->reloadStrategyTrigger->emitException($e);

            throw $e;
        } finally {
            $this->reloadStrategyTrigger->emitRequest($request);
        }
    }
}
