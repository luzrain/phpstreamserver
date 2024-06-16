<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\HttpDriver;
use Amp\Http\Server\ErrorHandler as ErrorHandlerInterface;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Server;

final class ErrorHandler implements ErrorHandlerInterface
{
    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        $errorPage = (new ErrorPage(
            code: $status,
            title: $reason ?? HttpStatus::getReason($status),
            exception: Functions::reportErrors() ? $this->findCausationException() : null,
        ));

        $response = new Response(
            headers: ['content-type' => 'text/html; charset=utf-8'],
            body: (string) $errorPage,
        );

        $response->setHeader('server', Server::VERSION_STRING);
        $response->setStatus($status, $reason);

        return $response;
    }

    private function findCausationException(): \Throwable|null
    {
        foreach (\debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10) as $trace) {
            if (($trace['object'] ?? null) instanceof HttpDriver && ($trace['function'] ?? null) === 'handleInternalServerError') {
                foreach ($trace['args'] ?? [] as $arg) {
                    if ($arg instanceof \Throwable) {
                        return $arg;
                    }
                }
                return null;
            }
        }
        return null;
    }
}
