<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http;

use Luzrain\PhpRunner\Exception\HttpException;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class Request
{
    /** @var resource */
    private mixed $resource;
    private RequestStream $requestStream;
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

            if ($this->hasPayload && ($this->contentLength <= 0 && !$this->isChunked)) {
                throw new HttpException(400, true);
            }

            if (!$this->hasPayload) {
                $this->isCompleted = true;
            }
        }

        $bodySize = $this->requestStream->getSize();

        if ($this->hasPayload && $this->maxBodySize > 0 && $bodySize > $this->maxBodySize) {
            throw new HttpException(413, true);
        }

        if ($this->hasPayload && $this->contentLength > 0 && $bodySize === $this->contentLength) {
            $this->isCompleted = true;
        }
    }

    private function init(string $firstLine): void
    {
        /** @var list<string> $firstLineParts */
        $firstLineParts = \sscanf($firstLine, '%s %s HTTP/%s');

        $this->isInitiated = true;
        $this->requestStream = new RequestStream($this->resource);
        $this->contentLength = (int) $this->requestStream->getHeader('content-length', '0');
        $this->isChunked = $this->requestStream->getHeader('transfer-encoding', '') === 'chunked';
        $this->method = $firstLineParts[0] ?? '';
        $this->uri = $firstLineParts[1] ?? '';
        $this->version = $firstLineParts[2] ?? '';
        $this->hasPayload = \in_array($this->method, ['POST', 'PUT', 'PATCH'], true);
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

        $psrRequest = new ServerRequest(
            method: $this->method,
            uri: $this->uri,
            serverParams: [...$_SERVER, ...[
                'SERVER_ADDR' => $serverAddr,
                'SERVER_PORT' => $serverPort,
                'REMOTE_ADDR' => $remoteAddr,
                'REMOTE_PORT' => $remotePort,
            ]],
        );

        foreach ($this->requestStream->getHeaders() as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, \array_map(\trim(...), \explode(',', $value)));
        }

        [$payload, $files] = $this->parsePayload($this->requestStream);

        return $psrRequest
            ->withProtocolVersion($this->version)
            ->withCookieParams($this->requestStream->getHeaderOptions('Cookie'))
            ->withParsedBody($payload)
            ->withUploadedFiles($files)
            ->withBody($this->requestStream)
        ;
    }

    private function parsePayload(RequestStream $requestStream): array
    {
        $payload = [];
        $files = [];

        if (!$this->hasPayload) {
            return [$payload, $files];
        }

        if ($requestStream->getHeaderValue('Content-Type') === 'application/x-www-form-urlencoded') {
            \parse_str($requestStream->getContents(), $payload);
        } elseif ($requestStream->getHeaderValue('Content-Type') === 'application/json') {
            $payload = (array) \json_decode($requestStream->getContents(), true);
        } elseif ($requestStream->isMultiPart()) {
            [$payload, $files] = $this->parseMultiPart($requestStream->getParts());
        }

        return [$payload, $files];
    }

    /**
     * @param \Generator<RequestStream> $parts
     */
    private function parseMultiPart(\Generator $parts): array
    {
        $payload = [];
        $files = [];
        $fileStructureStr = '';
        $fileStructureList = [];
        foreach ($parts as $part) {
            /** @var RequestStream $part */
            if ($part->isFile()) {
                $fileStructureStr .= $part->getName() . '&';
                $fileStructureList[] = new UploadedFile(
                    $part,
                    $part->getSize(),
                    UPLOAD_ERR_OK,
                    $part->getFileName(),
                    $part->getMimeType(),
                );
            } else {
                /** @psalm-suppress PossiblyNullArrayOffset */
                $payload[$part->getName()] = $part->getContents();
            }
        }
        if (!empty($fileStructureList)) {
            $i = 0;
            \parse_str($fileStructureStr, $files);
            \array_walk_recursive($files, static function (mixed &$item) use ($fileStructureList, &$i) {
                $item = $fileStructureList[$i++];
            });
        }

        return [$payload, $files];
    }
}
