<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Http\Psr7;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class UploadedFile implements UploadedFileInterface
{
    private bool $isMoved = false;

    public function __construct(private readonly HttpRequestStream $requestStream)
    {
    }

    public function getStream(): StreamInterface
    {
        if ($this->isMoved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }

        return $this->requestStream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->isMoved) {
            throw new \RuntimeException('Cannot move file after it has already been moved');
        }

        if (false === $file = \fopen($targetPath, 'w')) {
            return;
        }

        $this->requestStream->rewind();
        while (!$this->requestStream->eof()) {
            \fwrite($file, $this->requestStream->read(524288));
        }
        \fclose($file);
        $this->isMoved = true;
    }

    public function getSize(): int
    {
        return $this->requestStream->getSize();
    }

    public function getError(): int
    {
        return UPLOAD_ERR_OK;
    }

    public function getClientFilename(): string|null
    {
        return (null !== $filename = $this->requestStream->getHeaderOption('Content-Disposition', 'filename')) ? \trim($filename, '"') : null;
    }

    public function getClientMediaType(): string|null
    {
        return $this->requestStream->getHeader('Content-Type', 'application/octet-stream');
    }
}
