<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 * @psalm-require-implements MessageInterface
 */
trait MessageTrait
{
    /** @var array Map of all registered headers, as original name => array of values */
    private array $headers = [];

    /** @var array Map of lowercase header name => original name at registration */
    private array $headerNames = [];

    private string $protocol = '1.1';

    private StreamInterface|null $stream;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * @psalm-return static
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        if ($this->protocol === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    /**
     * @return array<array<array-key, string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[\strtolower($name)]);
    }

    /**
     * @return array<array-key, string>
     */
    public function getHeader(string $name): array
    {
        return $this->hasHeader($name) ? $this->headers[$this->headerNames[\strtolower($name)]] : [];
    }

    public function getHeaderLine(string $name): string
    {
        return \implode(', ', $this->getHeader($name));
    }

    /**
     * @param string|array<string> $value
     * @psalm-return static
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $normalizedName = $this->normalizeHeaderName($name);
        $value = $this->normalizeHeaderValue($value);
        $new = clone $this;
        if (isset($new->headerNames[$normalizedName])) {
            unset($new->headers[$new->headerNames[$normalizedName]]);
        }
        $new->headerNames[$normalizedName] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * @param string|array<string> $value
     * @psalm-return static
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $new = clone $this;
        $new->setHeaders([$name => $value]);

        return $new;
    }

    /**
     * @psalm-return static
     */
    public function withoutHeader(string $name): MessageInterface
    {
        if (!$this->hasHeader($name)) {
            return $this;
        }

        $normalizedName = $this->normalizeHeaderName($name);
        $new = clone $this;
        unset($new->headers[$this->headerNames[$normalizedName]], $new->headerNames[$normalizedName]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->stream ?? new StringStream('');
    }

    /**
     * @psalm-return static
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($this->stream === $body) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    /**
     * @param array<string, string|array<string>> $headers
     */
    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $normalizedName = $this->normalizeHeaderName($name);
            $value = $this->normalizeHeaderValue($value);
            if (isset($this->headerNames[$normalizedName])) {
                $name = $this->headerNames[$normalizedName];
                $this->headers[$name] = \array_merge($this->headers[$name], $value);
            } else {
                $this->headerNames[$normalizedName] = $name;
                $this->headers[$name] = $value;
            }
        }
    }

    /**
     * @see https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * @throws \InvalidArgumentException
     */
    private function normalizeHeaderName(string $name): string
    {
        if (!\preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $name)) {
            throw new \InvalidArgumentException('Header name must be an RFC 7230 compatible string');
        }

        return \strtolower($name);
    }

    /**
     * @see https://tools.ietf.org/html/rfc7230#section-3.2
     *
     * @param string|array<string> $value
     * @return list<string>
     * @throws \InvalidArgumentException
     * @psalm-suppress DocblockTypeContradiction
     */
    private function normalizeHeaderValue(string|array $value): array
    {
        $value = \is_array($value) ? \array_values($value) : \explode(',', $value);

        if (empty($value)) {
            throw new \InvalidArgumentException('Header value must be a string or an array of strings, empty array given');
        }

        $normalizedValues = [];
        foreach ($value as $v) {
            if (!\is_string($v) || !\preg_match('/^[ \t\x21-\x7E\x80-\xFF]*$/D', $v)) {
                throw new \InvalidArgumentException('Header values must be RFC 7230 compatible strings');
            }
            $normalizedValues[] = \trim($v, " \t");
        }

        return $normalizedValues;
    }
}
