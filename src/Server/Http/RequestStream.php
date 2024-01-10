<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http;

final class RequestStream
{
    private array $headers = [];
    private int $bodyOffset;
    /** @var list<self> */
    private array $parts = [];
    private array $headerOptionsCache = [];

    /**
     * @param resource $stream
     */
    public function __construct(private mixed $stream)
    {
        \rewind($this->stream);
        $endOfHeaders = false;
        $bufferSize = 32768;

        while (false !== ($line = \stream_get_line($this->stream, $bufferSize, "\r\n"))) {
            // Empty line cause by double new line, we reached the end of the headers section
            if ($line === '') {
                $endOfHeaders = true;
                break;
            }
            $parts = \explode(':', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }
            $key = \strtolower($parts[0]); // @todo: Remove strtolower?
            $value = \trim($parts[1] ?? '');
            $this->headers[$key] = isset($this->headers[$key]) ? "{$this->headers[$key]}, $value" : $value;
        }

        if ($endOfHeaders === false) {
            throw new \InvalidArgumentException('Header is not valid');
        }

        $this->bodyOffset = \ftell($stream);

        if (!$this->isMultiPart()) {
            return;
        }

        // Parse multipart
        if (null === ($boundary = $this->getHeaderOption('Content-Type', 'boundary'))) {
            throw new \InvalidArgumentException("Can't find boundary in content type");
        }

        $separator = "--$boundary";
        $partOffset = 0;
        $endOfBody = false;

        while (false !== ($line = \stream_get_line($this->stream, $bufferSize, "\r\n"))) {
            if ($line === $separator || $line === "$separator--") {
                if ($partOffset > 0) {
                    $currentOffset = \ftell($this->stream);
                    $partLength = $currentOffset - $partOffset - \strlen($line) - 4;

                    // Copy part in a new stream @todo: Rewrite to use ONE stream for memory optimization
                    $partStream = \fopen('php://temp', 'rw');
                    \stream_copy_to_stream($this->stream, $partStream, $partLength, $partOffset);
                    $this->parts[] = new self($partStream);

                    // Reset current stream offset
                    \fseek($this->stream, $currentOffset);
                }

                if ($line === "$separator--") {
                    $endOfBody = true;
                    break;
                }

                $partOffset = \ftell($this->stream);
            }
        }

        if (\count($this->parts) === 0 || $endOfBody === false) {
            throw new \LogicException("Can't find multi-part content");
        }
    }

    public function isMultiPart(): bool
    {
        /** @psalm-suppress PossiblyNullArgument */
        return \str_starts_with($this->getHeader('Content-Type', ''), 'multipart/');
    }

    public function getBodyStream(): mixed
    {
        return $this->stream;
    }

    public function getBody(): string
    {
        return \stream_get_contents($this->stream, -1, $this->bodyOffset);
    }

    public function getBodySize(): int
    {
        return \fstat($this->stream)['size'] - $this->bodyOffset;
    }

    public function getHeaderSize(): int
    {
        return \max(0, $this->bodyOffset - 2);
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $key, string|null $default = null): string|null
    {
        return $this->headers[\strtolower($key)] ?? $default;
    }

    public function getHeaderValue(string $key, string|null $default = null): string|null
    {
        return ($this->headerOptionsCache[$key] ??= $this->parseHeaderContent($this->getHeader($key)))[0] ?? $default;
    }

    public function getHeaderOptions(string $key): array
    {
        return ($this->headerOptionsCache[$key] ??= $this->parseHeaderContent($this->getHeader($key)))[1];
    }

    public function getHeaderOption(string $key, string $option, string|null $default = null): string|null
    {
        return $this->getHeaderOptions($key)[$option] ?? $default;
    }

    public function isFile(): bool
    {
        return $this->getHeaderOption('Content-Disposition', 'filename') !== null;
    }

    /**
     * @psalm-suppress NullableReturnStatement
     * @psalm-suppress InvalidNullableReturnType
     */
    public function getMimeType(): string
    {
        return $this->getHeader('Content-Type', 'application/octet-stream');
    }

    public function getName(): string|null
    {
        return (null !== $val = $this->getHeaderOption('Content-Disposition', 'name')) ? \trim($val, ' "') : $val;
    }

    public function getFileName(): string|null
    {
        return (null !== $val = $this->getHeaderOption('Content-Disposition', 'filename')) ? \trim($val, ' "') : $val;
    }

    /**
     * @return list<self>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    private function parseHeaderContent(string|null $content): array
    {
        if ($content !== null) {
            \parse_str(\preg_replace('/;\s?/', '&', $content), $values);
            if (($firstKey = \array_key_first($values)) !== null && $values[$firstKey] === '') {
                \array_shift($values);
                $headerValue = $firstKey;
            }
        }

        return [$headerValue ?? null, $values ?? []];
    }
}
