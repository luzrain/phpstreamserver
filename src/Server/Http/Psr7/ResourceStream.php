<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http\Psr7;

use Psr\Http\Message\StreamInterface;

final class ResourceStream implements StreamInterface
{
    /** @var resource|null */
    private mixed $resource;
    private bool $seekable;
    private bool $readable;
    private bool $writable;

    /**
     * @param resource $resource
     */
    public function __construct(mixed $resource)
    {
        if (!\is_resource($resource) || \get_resource_type($resource) !== 'stream') {
            throw new \InvalidArgumentException(sprintf('%s::__construct(): Argument #1 ($resource) must be of type resource, %s given', self::class, \get_debug_type($resource)));
        }

        $this->resource = $resource;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string
    {
        if ($this->isSeekable()) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    public function close(): void
    {
        $resource = $this->detach();
        if ($resource !== null) {
            \fclose($resource);
        }
    }

    /**
     * @return resource|null
     */
    public function detach(): mixed
    {
        if (!isset($this->resource)) {
            return null;
        }

        try {
            return $this->resource;
        } finally {
            unset($this->resource);
            $this->seekable = false;
            $this->readable = false;
            $this->writable = false;
        }
    }

    public function getSize(): int|null
    {
        return !isset($this->resource) ? null : \fstat($this->resource)['size'] ?? null;
    }

    public function tell(): int
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is detached');
        }

        \set_error_handler(static function (int $type, string $message) {
            throw new \RuntimeException('Unable to determine stream position: ' . $message);
        });

        try {
            return \ftell($this->resource);
        } finally {
            \restore_error_handler();
        }
    }

    public function eof(): bool
    {
        return !isset($this->resource) || \feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable ??= isset($this->resource) && $this->getMetadata('seekable');
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable');
        }

        if (\fseek($this->resource, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position "' . $offset . '" with whence ' . \var_export($whence, true));
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if (isset($this->writable)) {
            return $this->writable;
        }

        if (!\is_string($mode = $this->getMetadata('mode'))) {
            return $this->writable = false;
        }

        return $this->writable = (
            \str_contains($mode, 'w') ||
            \str_contains($mode, '+') ||
            \str_contains($mode, 'x') ||
            \str_contains($mode, 'c') ||
            \str_contains($mode, 'a')
        );
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('Stream is non-writable');
    }

    public function isReadable(): bool
    {
        if (isset($this->readable)) {
            return $this->readable;
        }

        if (!\is_string($mode = $this->getMetadata('mode'))) {
            return $this->readable = false;
        }

        return $this->readable = (\str_contains($mode, 'r') || \str_contains($mode, '+'));
    }

    public function read(int $length): string
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isReadable()) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        \set_error_handler(static function (int $type, string $message) {
            throw new \RuntimeException('Unable to read from stream: ' . $message);
        });

        try {
            return \fread($this->resource, $length);
        } finally {
            \restore_error_handler();
        }
    }

    public function getContents(): string
    {
        if (!isset($this->resource)) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isReadable()) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        \set_error_handler(static function (int $type, string $message) {
            throw new \RuntimeException('Unable to read stream contents: ' . $message);
        });

        try {
            return \stream_get_contents($this->resource, null, 0);
        } finally {
            \restore_error_handler();
        }
    }

    public function getMetadata(string|null $key = null): mixed
    {
        if (!isset($this->resource)) {
            return $key ? null : [];
        }
        $metadata = \stream_get_meta_data($this->resource);

        return $key === null ? $metadata : $metadata[$key] ?? null;
    }
}
