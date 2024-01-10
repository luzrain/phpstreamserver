<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http;

use Luzrain\PhpRunner\Exception\HttpException;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class Request
{
    private bool $isCompleted = false;
    /** @var resource */
    private mixed $request;
    private string $method;
    private string $uri;
    private string $version;
    private int $contentLength;
    private bool $chunked;
    private bool $hasPayload;
    private RequestStream $storage;

    public function __construct(
        private readonly int $maxHeaderSize,
        private readonly int $maxBodySize,
    ) {
        $this->request = \fopen('php://temp', 'rw');
    }

    /**
     * @throws HttpException
     */
    public function parse(string $buffer): void
    {
        if ($this->isCompleted) {
            return;
        }

        \fputs($this->request, $buffer);

        // Parse headers
        if (!isset($this->storage)) {
            $this->storage = new RequestStream($this->request);
            [$this->method, $this->uri, $this->version] = $this->parseFirstLine(\strstr($buffer, "\r\n", true));
            $this->contentLength = (int) $this->storage->getHeader('content-length', '0');
            $this->chunked = $this->storage->getHeader('transfer-encoding', '') === 'chunked';
            $this->hasPayload = \in_array($this->method, ['POST', 'PUT', 'PATCH'], true);

            if ($this->method === '' || $this->uri === '' || $this->version === '') {
                throw new HttpException(400, true);
            }

            if (!\in_array($this->version, ['1.0', '1.1'], true)) {
                throw new HttpException(505, true);
            }

            if ($this->hasPayload && ($this->contentLength <= 0 && !$this->chunked)) {
                throw new HttpException(400, true);
            }
        }

        $bodySize = $this->storage->getBodySize();
        $headerSize = $this->storage->getHeaderSize();

        if ($headerSize >= $this->maxHeaderSize) {
            throw new HttpException(413, true);
        }

        if ($this->hasPayload && $this->maxBodySize > 0 && $bodySize > $this->maxBodySize) {
            throw new HttpException(413, true);
        }

        if ($this->hasPayload && $this->contentLength > 0 && $bodySize === $this->contentLength) {
            $this->isCompleted = true;
        }

        if (!$this->hasPayload) {
            $this->isCompleted = true;
        }
    }

    /**
     * @preturn array{0: string, 1: string, 2: string}
     */
    private function parseFirstLine(string $line): array
    {
        /** @var list<string> $parts */
        $parts = \sscanf($line, '%s %s HTTP/%s');

        return [$parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? ''];
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

        foreach ($this->storage->getHeaders() as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, \array_map(\trim(...), \explode(',', $value)));
        }

        [$payload, $files] = $this->parsePayload($this->storage);

        return $psrRequest
            ->withProtocolVersion($this->version)
            ->withCookieParams($this->storage->getHeaderOptions('Cookie'))
            ->withParsedBody($payload)
            ->withUploadedFiles($files)
            ->withBody(Stream::create($this->storage->getBodyStream()))
        ;
    }

    protected function parsePayload(RequestStream $storage): array
    {
        $payload = [];
        $files = [];
        if ($storage->getHeaderValue('Content-Type') === 'application/x-www-form-urlencoded') {
            \parse_str($storage->getBody(), $payload);
        } elseif ($storage->getHeaderValue('Content-Type') === 'application/json') {
            $payload = (array) \json_decode($storage->getBody(), true);
        } elseif ($storage->isMultiPart()) {
            $fileStructureStr = '';
            $fileStructureList = [];
            foreach ($storage->getParts() as $part) {
                if ($part->isFile()) {
                    $fileStructureStr .= $part->getName() . '&';
                    $fileStructureList[] = new UploadedFile(
                        $part->getBodyStream(),
                        $part->getBodySize(),
                        UPLOAD_ERR_OK,
                        $part->getFileName(),
                        $part->getMimeType(),
                    );
                } else {
                    /** @psalm-suppress PossiblyNullArrayOffset */
                    $payload[$part->getName()] = $part->getBody();
                }
            }
            if (!empty($fileStructureList)) {
                $i = 0;
                \parse_str($fileStructureStr, $files);
                \array_walk_recursive($files, static function (mixed &$item) use ($fileStructureList, &$i) {
                    $item = $fileStructureList[$i++];
                });
            }
        }

        return [$payload, $files];
    }
}
