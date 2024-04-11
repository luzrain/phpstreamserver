<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http\Psr7;

use Psr\Http\Message\StreamInterface;

final class HttpRequestStream implements StreamInterface
{
    private const BUFFER_SIZE = 32768;

    private readonly int $globalOffset;
    private readonly int $globalBodyOffset;
    private int $bodyPointer;
    private readonly int|null $size;
    private bool|null $isChunked = null;
    private bool|null $isMultipart = null;
    private array $headers = [];
    private array $headerOptionsCache = [];

    /**
     * @param resource $stream
     */
    public function __construct(private mixed &$stream, int $offset = 0, int|null $size = null)
    {
        \fseek($this->stream, $offset);
        $this->globalOffset = $offset;
        $this->size = $size;

        while (false !== ($line = \stream_get_line($this->stream, self::BUFFER_SIZE, "\r\n"))) {
            // Empty line cause by double new line, we reached the end of the headers section
            if ($line === '') {
                break;
            }
            $parts = \explode(':', $line, 2);
            if (\count($parts) !== 2) {
                continue;
            }
            $key = \strtolower($parts[0]);
            $value = \trim($parts[1]);
            $this->headers[$key] = isset($this->headers[$key]) ? "{$this->headers[$key]}, $value" : $value;
        }

        $this->globalBodyOffset = \ftell($this->stream);
        $this->bodyPointer = 0;

        \fseek($this->stream, 0, SEEK_END);

        if ($this->isChunked()) {
            \stream_filter_append($this->stream, 'dechunk', STREAM_FILTER_READ);
        }
    }

    public function isMultiPart(): bool
    {
        /** @psalm-suppress PossiblyNullArgument */
        return $this->isMultipart ??= \str_starts_with($this->getHeader('Content-Type', ''), 'multipart/');
    }

    public function isChunked(): bool
    {
        return $this->isChunked ??= $this->getHeader('Transfer-Encoding', '') === 'chunked';
    }

    public function getHeaderSize(): int
    {
        return \max(0, $this->globalBodyOffset - $this->globalOffset - 4);
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

    public function getName(): string|null
    {
        return (null !== $name = $this->getHeaderOption('Content-Disposition', 'name')) ? \trim($name, '"') : null;
    }

    /**
     * @return \Generator<self>
     */
    public function getParts(): \Generator
    {
        if (null === ($boundary = $this->getHeaderOption('Content-Type', 'boundary'))) {
            throw new \InvalidArgumentException("Can't find boundary in content type");
        }

        \fseek($this->stream, $this->globalBodyOffset);
        $separator = "--$boundary";
        $partCount = 0;
        $partOffset = 0;
        $endOfBody = false;

        while (false !== ($line = \stream_get_line($this->stream, self::BUFFER_SIZE, "\r\n"))) {
            if ($line !== $separator && $line !== "$separator--") {
                continue;
            }

            if ($partOffset > 0) {
                $partCount++;
                $currentOffset = \ftell($this->stream);
                $partStartPosition = $partOffset;
                $partLength = $currentOffset - $partStartPosition - \strlen($line) - 4;
                yield new self($this->stream, $partStartPosition, $partLength);
                \fseek($this->stream, $currentOffset);
            }

            if ($line === "$separator--") {
                $endOfBody = true;
                break;
            }

            $partOffset = \ftell($this->stream);
        }

        if ($partCount === 0 || $endOfBody === false) {
            throw new \InvalidArgumentException("Can't find multi-part content");
        }
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

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        // nothing
    }

    /**
     * @return resource
     */
    public function detach(): mixed
    {
        $bodyStream = \fopen('php://temp', 'rw');
        \stream_copy_to_stream($this->stream, $bodyStream, $this->getSize(), $this->globalBodyOffset);

        return $bodyStream;
    }

    public function getSize(): int
    {
        return ($this->size ?? \fstat($this->stream)['size']) - $this->getHeaderSize() - 4;
    }

    public function tell(): int
    {
        return $this->bodyPointer;
    }

    public function eof(): bool
    {
        return \feof($this->stream) || $this->tell() >= $this->getSize();
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence === SEEK_CUR) {
            $offset += $this->bodyPointer;
        } elseif ($whence === SEEK_END) {
            $offset += $this->getSize();
        }

        $bodyPointer = $offset;
        $globalOffset = $this->globalBodyOffset + $offset;
        if ($this->size !== null && $offset > $this->getSize()) {
            $globalOffset = $this->globalOffset + $this->size;
            $bodyPointer = $this->getSize();
        }
        $this->bodyPointer = $bodyPointer;
        \fseek($this->stream, $globalOffset);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('Stream is non-writable');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $remainingSize = $this->getSize() - $this->tell();
        if ($this->size !== null && $length > $remainingSize) {
            $length = $remainingSize;
        }
        $this->bodyPointer += $length;

        return \fread($this->stream, $length);
    }

    public function getContents(): string
    {
        if ($this->isMultiPart()) {
            return '';
        }

        $length = $this->size === null ? null : $this->getSize();

        return \stream_get_contents($this->stream, $length, $this->globalBodyOffset);
    }

    public function getMetadata(string|null $key = null): mixed
    {
        $meta = \stream_get_meta_data($this->stream);

        return $key === null ? $meta : $meta[$key] ?? null;
    }
}
