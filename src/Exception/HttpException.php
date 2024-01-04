<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Exception;

use Luzrain\PhpRunner\Internal\Functions;
use Luzrain\PhpRunner\Server\Http\ErrorPage;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

final class HttpException extends \Exception
{
    private ResponseInterface $response;

    public function __construct(private int $httpCode = 500, private bool $closeConnection = false, private \Throwable|null $previous = null)
    {
        $this->response = new \Nyholm\Psr7\Response(status: $this->httpCode);

        parent::__construct($this->response->getReasonPhrase(), $this->httpCode, $this->previous);
    }

    public function getResponse(): ResponseInterface
    {
        $errorPage = (new ErrorPage(
            code: $this->httpCode,
            title: $this->response->getReasonPhrase(),
            exception: $this->previous !== null && Functions::reportErrors() ? $this->previous : null,
        ));

        if ($this->closeConnection) {
            $this->response = $this->response->withHeader('Connection', 'close');
        }

        return $this
            ->response
            ->withHeader('Content-Type', 'text/html')
            ->withBody(Stream::create((string) $errorPage))
        ;
    }
}
