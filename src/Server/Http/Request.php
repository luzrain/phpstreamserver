<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http;

use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\Server\Http\Psr7\HttpRequestStream;
use Luzrain\PhpRunner\Server\Http\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class Request
{
    /** @var resource */
    private mixed $resource;
    private HttpRequestStream $requestStream;
    private bool $isInitiated = false;
    private bool $isCompleted = false;
    private bool $isChunked;
    private bool $hasPayload;
    private string $method;
    private string $uri;
    private string $version;
    private int $contentLength;

    public function __construct(
        private readonly int $maxHeaderSize,
        private readonly int $maxBodySize,
    ) {
        $this->resource = \fopen('php://temp', 'rw');
    }

    /**
     * @throws HttpException
     */
    public function parse(string $buffer): void
    {
        if ($this->isCompleted) {
            return;
        }

        \fwrite($this->resource, $buffer);

        if ($this->isInitiated === false) {
            $this->init(firstLine: \strstr($buffer, "\r\n", true));

            if ($this->requestStream->getHeaderSize() >= $this->maxHeaderSize) {
                throw new HttpException(413, true);
            }

            if ($this->method === '' || $this->uri === '' || $this->version === '') {
                throw new HttpException(400, true);
            }

            if (!\in_array($this->version, ['1.0', '1.1'], true)) {
                throw new HttpException(505, true);
            }

            if (!$this->hasPayload) {
                $this->isCompleted = true;
            }
        }

        $bodySize = $this->requestStream->getSize();

        if ($this->hasPayload && $this->maxBodySize > 0 && $bodySize > $this->maxBodySize) {
            throw new HttpException(413, true);
        }

        if ($this->hasPayload && $bodySize === $this->contentLength) {
            $this->isCompleted = true;
        }
    }

    private function init(string $firstLine): void
    {
        /** @var list<string> $firstLineParts */
        $firstLineParts = \sscanf($firstLine, '%s %s HTTP/%s');

        $this->isInitiated = true;
        $this->requestStream = new HttpRequestStream($this->resource);
        $this->contentLength = (int) $this->requestStream->getHeader('content-length', '0');
        $this->isChunked = $this->requestStream->getHeader('transfer-encoding', '') === 'chunked';
        $this->method = $firstLineParts[0] ?? '';
        $this->uri = $firstLineParts[1] ?? '';
        $this->version = $firstLineParts[2] ?? '';
        $this->hasPayload = \in_array($this->method, ['POST', 'PUT', 'PATCH'], true) && ($this->contentLength > 0 || $this->isChunked);
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function getPsrServerRequest(string $serverAddr, string $serverPort, string $remoteAddr, string $remotePort): ServerRequestInterface
    {
        if (!$this->isCompleted()) {
            throw new \LogicException('ServerRequest cannot be created until request is complete');
        }

        return new ServerRequest(
            requestStream: $this->requestStream,
            method: $this->method,
            uri: $this->uri,
            protocol: $this->version,
            serverParams: [...$_SERVER, ...[
                'SERVER_ADDR' => $serverAddr,
                'SERVER_PORT' => $serverPort,
                'REMOTE_ADDR' => $remoteAddr,
                'REMOTE_PORT' => $remotePort,
            ]],
        );
    }
}
