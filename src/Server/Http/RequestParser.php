<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http;

use Luzrain\PHPStreamServer\Exception\HttpException;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\Server\Http\Psr7\HttpRequestStream;
use Luzrain\PHPStreamServer\Server\Http\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class RequestParser
{
    /** @var resource */
    private mixed $stream;
    private HttpRequestStream $requestStream;
    private bool $isInitiated = false;
    private bool $isCompleted = false;
    private bool $isHeadersCompleted = false;
    private bool $hasPayload;
    private string $method;
    private string $uri;
    private string $version;
    private int $contentLength;

    public function __construct(
        private readonly int $maxHeaderSize,
        private readonly int $maxBodySize,
    ) {
        $this->stream = \fopen('php://temp', 'rw');
    }

    /**
     * @throws HttpException
     */
    public function parse(string $buffer): void
    {
        if ($this->isCompleted) {
            return;
        }

        \fseek($this->stream, 0, SEEK_END);
        \fwrite($this->stream, $buffer);

        if (!$this->isHeadersEnd()) {
            return;
        }

        if ($this->isInitiated === false) {
            \rewind($this->stream);
            $this->init(firstLine: \stream_get_line($this->stream, $this->maxHeaderSize, "\r\n"));

            if ($this->method === '' || $this->uri === '' || $this->version === '') {
                throw new HttpException(httpCode: 400, closeConnection: true);
            }

            if (!\in_array($this->version, ['1.0', '1.1'], true)) {
                throw new HttpException(httpCode: 505, closeConnection: true);
            }

            if (!$this->hasPayload) {
                $this->isCompleted = true;
            }
        }

        $bodySize = $this->requestStream->getSize();

        if ($this->hasPayload && $this->maxBodySize > 0 && $bodySize > $this->maxBodySize) {
            throw new HttpException(httpCode: 413, closeConnection: true);
        }

        if ($this->hasPayload && $this->contentLength > 0 && $bodySize === $this->contentLength) {
            $this->isCompleted = true;
        }

        if ($this->requestStream->isChunked() && $this->hasPayload && \str_contains($buffer, "0\r\n\r\n")) {
            $this->isCompleted = true;
        }
    }

    /**
     * @throws HttpException
     */
    private function isHeadersEnd(): bool
    {
        if ($this->isHeadersCompleted) {
            return true;
        }

        \rewind($this->stream);
        while (false !== ($line = \stream_get_line($this->stream, $this->maxHeaderSize, "\r\n"))) {
            // Empty line cause by double new line, we reached the end of the headers section
            if ($line === '') {
                $this->isHeadersCompleted = true;
                break;
            }
        }

        $headersSize = \ftell($this->stream) ?: 0;
        if ($headersSize >= $this->maxHeaderSize) {
            throw new HttpException(httpCode: 431, closeConnection: true);
        }

        return $this->isHeadersCompleted;
    }

    private function init(string $firstLine): void
    {
        /** @var list<string> $firstLineParts */
        $firstLineParts = \sscanf($firstLine, '%s %s HTTP/%s');

        $this->isInitiated = true;
        $this->requestStream = new HttpRequestStream($this->stream);
        $this->contentLength = (int) $this->requestStream->getHeader('content-length', '0');
        $this->method = $firstLineParts[0] ?? '';
        $this->uri = $firstLineParts[1] ?? '';
        $this->version = $firstLineParts[2] ?? '';
        $this->hasPayload = \in_array($this->method, ['POST', 'PUT', 'PATCH'], true) && ($this->contentLength > 0 || $this->requestStream->isChunked());
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function getPsrServerRequest(string $serverAddr, int $serverPort, string $remoteAddr, int $remotePort): ServerRequestInterface
    {
        if (!$this->isCompleted()) {
            throw new \LogicException('ServerRequest cannot be created until request is complete');
        }

        return new ServerRequest(
            requestStream: $this->requestStream,
            method: $this->method,
            uri: $this->uri,
            protocol: $this->version,
            serverParams: [
                'REMOTE_ADDR' => $remoteAddr,
                'REMOTE_PORT' => $remotePort,
                'SERVER_ADDR' => $serverAddr,
                'SERVER_PORT' => $serverPort,
                'SERVER_SOFTWARE' => Server::VERSION_STRING,
            ],
        );
    }
}
