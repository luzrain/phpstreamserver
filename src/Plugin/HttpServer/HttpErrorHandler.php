<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\HttpServer;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Driver\Internal\AbstractHttpDriver;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Luzrain\PHPStreamServer\Internal\Functions;
use Luzrain\PHPStreamServer\Server;

final readonly class HttpErrorHandler implements ErrorHandler
{
    public function handleError(int $status, ?string $reason = null, ?Request $request = null): Response
    {
        $exception = $this->findCausationExceptionInStackTrace();

        $errorPage = (new ErrorPage(
            code: $status,
            title: $reason ?? HttpStatus::getReason($status),
            exception: Functions::reportErrors() ? $exception : null,
        ));

        $response = new Response(
            headers: ['content-type' => 'text/html; charset=utf-8'],
            body: (string) $errorPage,
        );

        $response->setHeader('server', Server::VERSION_STRING);
        $response->setStatus($status, $reason);

        return $response;
    }

    /**
     * A bit hacky but this is only possible way to get causation throwable since amp ErrorHandler doesn't provide exception itself
     */
    private function findCausationExceptionInStackTrace(): \Throwable|null
    {
        foreach (\debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10) as $trace) {
            if (($trace['object'] ?? null) instanceof AbstractHttpDriver && ($trace['function'] ?? null) === 'handleInternalServerError') {
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
