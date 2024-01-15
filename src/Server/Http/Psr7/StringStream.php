<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http\Psr7;

use Psr\Http\Message\StreamInterface;

final class StringStream implements StreamInterface
{
    private string $string;
    private int $size;
    private int $pointer = 0;

    public function __construct(string|\Stringable $string)
    {
        $this->string = (string) $string;
        $this->size = \strlen($this->string);
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        // nothing
    }

    public function detach(): null
    {
        return null;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->pointer;
    }

    public function eof(): bool
    {
        return $this->pointer >= $this->size;
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence === SEEK_CUR) {
            $offset += $this->tell();
        } else if ($whence === SEEK_END) {
            $offset += $this->getSize();
        }
        if ($offset > $this->getSize()) {
            $offset = $this->getSize();
        }
        $this->pointer = $offset;
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
        if ($length > $remainingSize) {
            $length = $remainingSize;
        }
        $this->pointer += $length;

        return \substr($this->string, $this->pointer, $length);
    }

    public function getContents(): string
    {
        return $this->string;
    }

    public function getMetadata(string|null $key = null): null
    {
        return null;
    }
}
