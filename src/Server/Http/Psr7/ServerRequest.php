<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

final class ServerRequest implements ServerRequestInterface
{
    use MessageTrait;

    private array $attributes = [];
    private array $cookieParams;
    private array|object|null $parsedBody = [];
    private array $queryParams = [];
    private array $serverParams;
    /** @var array<UploadedFileInterface> */
    private array $uploadedFiles = [];
    private string $method;
    private string|null $requestTarget;
    private UriInterface $uri;

    public function __construct(HttpRequestStream $requestStream, string $method, string $uri, string $protocol, array $serverParams)
    {
        $this->stream = $requestStream;
        $this->method = $method;
        $this->uri = new Uri($uri);
        $this->protocol = $protocol;
        $this->setHeaders($requestStream->getHeaders());
        \parse_str($this->uri->getQuery(), $this->queryParams);
        $this->cookieParams = $requestStream->getHeaderOptions('Cookie');
        $this->updatePayloadFromRequestStream($requestStream);

        if (!$this->hasHeader('Host')) {
            $this->updateHostFromUri();
        }

        $this->serverParams = [...$serverParams, ...[
            'SERVER_NAME' => $this->uri->getHost(),
            'SERVER_PROTOCOL' => 'HTTP/' . $this->getProtocolVersion(),
            'REQUEST_URI' => $this->uri->getPath(),
            'QUERY_STRING' => $this->uri->getQuery(),
            'REQUEST_METHOD' => $this->getMethod(),
        ]];

        if ($this->serverParams['QUERY_STRING'] !== '') {
            $this->serverParams['REQUEST_URI'] .= '?' . $this->serverParams['QUERY_STRING'];
        }

        if ($this->uri->getScheme() === 'https') {
            $this->serverParams['HTTPS'] = 'on';
        }
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @return static
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @return static
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @return static
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /**
     * @psalm-suppress DocblockTypeContradiction
     * @psalm-param array|object|null $data
     * @return static
     */
    public function withParsedBody(mixed $data): ServerRequestInterface
    {
        if (!\is_array($data) && !\is_object($data) && $data !== null) {
            throw new \InvalidArgumentException(\sprintf('%s::withParsedBody(): Argument #1 ($data) must be of type array|object|null, %s given', self::class, \get_debug_type($data)));
        }

        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @psalm-suppress MethodSignatureMismatch
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * @return static
     */
    public function withAttribute(string $name, mixed $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    /**
     * @return static
     */
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (false === \array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();

        if ($target !== '' && $query !== '') {
            $target .= '?' . $query;
        }

        return $target ?: '/';
    }

    /**
     * @return static
     */
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        if ($this->requestTarget === $requestTarget) {
            return $this;
        }

        if (\preg_match('/\s/', $requestTarget)) {
            throw new \InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return static
     */
    public function withMethod(string $method): RequestInterface
    {
        if ($this->method === $method) {
            return $this;
        }

        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * @return static
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $new->updateHostFromUri();
        }

        return $new;
    }

    private function updateHostFromUri(): void
    {
        if ('' === $host = $this->uri->getHost()) {
            return;
        }

        if (null !== ($port = $this->uri->getPort())) {
            $host .= ':' . $port;
        }

        $this->headerNames['host'] ??= 'Host';
        $this->headers = [$this->headerNames['host'] => [$host]] + $this->headers;
    }

    private function updatePayloadFromRequestStream(HttpRequestStream $requestStream): void
    {
        if (!\in_array($this->method, ['POST', 'PUT', 'PATCH'], true)) {
            return;
        } elseif ($requestStream->getHeaderValue('Content-Type') === 'application/x-www-form-urlencoded') {
            \parse_str($requestStream->getContents(), $this->parsedBody);
        } elseif ($requestStream->getHeaderValue('Content-Type') === 'application/json') {
            $this->parsedBody = (array) \json_decode($requestStream->getContents(), true);
        } elseif ($requestStream->isMultiPart()) {
            [$this->parsedBody, $this->uploadedFiles] = $this->parseMultiPart($requestStream->getParts());
        }
    }

    /**
     * @param \Generator<HttpRequestStream> $parts
     */
    private function parseMultiPart(\Generator $parts): array
    {
        $payload = [];
        $files = [];
        $fileStructureStr = '';
        $payloadStructureStr = '';
        $fileStructureList = [];
        foreach ($parts as $part) {
            /** @var HttpRequestStream $part */
            if (null === $name = $part->getName()) {
                continue;
            }
            if ($part->isFile()) {
                $fileStructureStr .= "$name=0&";
                $fileStructureList[] = new UploadedFile($part);
            } else {
                $payloadStructureStr .= "$name={$part->getContents()}&";
            }
        }

        if ($fileStructureList !== []) {
            $i = 0;
            \parse_str($fileStructureStr, $files);
            \array_walk_recursive($files, static function (mixed &$item) use (&$fileStructureList, &$i) {
                $item = $fileStructureList[$i++];
            });
        }

        \parse_str($payloadStructureStr, $payload);

        return [$payload, $files];
    }
}
