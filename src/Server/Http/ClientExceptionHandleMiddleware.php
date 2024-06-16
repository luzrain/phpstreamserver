<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http;

use Amp\Http\Server\ClientException;
use Amp\Http\Server\HttpErrorException;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;

final class ClientExceptionHandleMiddleware implements Middleware
{
    /**
     * @throws HttpErrorException
     */
    public function handleRequest(Request $request, RequestHandler $requestHandler): Response
    {
        try {
            return $requestHandler->handleRequest($request);
        } catch (ClientException $e) {
            throw new HttpErrorException($e->getCode(), $e->getMessage(), $e);
        }
    }
}
