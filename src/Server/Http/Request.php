<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http;

use Luzrain\PhpRunner\Exception\HttpException;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class Request
{
    private bool $headersParsed = false;
    private bool $isCompleted = false;
    private string $method = '';
    private string $uri = '';
    private string $version = '';
    private array $headers = [];
    private array $cookie = [];
    private int $contentLength = 0;
    private mixed $body = null;

    public function __construct(
        private readonly int $maxHeaderSize,
        private readonly int $maxBodySize,
    ) {
    }

    /**
     * @throws HttpException
     */
    public function parse(string $buffer): void
    {
        if ($this->isCompleted) {
            return;
        }

        if (!$this->headersParsed) {
            $crlfPos = \strpos($buffer, "\r\n\r\n");// ?: throw bad request;
            $header = \substr($buffer, 0, $crlfPos + 2);
            $this->parseHeader($header);
            $body = \substr($buffer, $crlfPos + 4);
        } else {
            $body = $buffer;
        }

        $this->body ??= \fopen('php://temp', 'rw');
        \fputs($this->body, $body);
        $bodySize = \fstat($this->body)['size'];

        if ($this->maxBodySize > 0 && $bodySize > $this->maxBodySize) {
            throw new HttpException(413, true);
        }

        if ($this->contentLength > 0 && $bodySize > $this->contentLength) {
            \ftruncate($this->body, $this->contentLength);
            $bodySize = $this->contentLength;
        }

        if ($this->contentLength === 0 || $bodySize === $this->contentLength) {
            $this->isCompleted = true;
            \fseek($this->body, 0);
        }
    }

    private function parseHeader(string $header): void
    {
        if (\strlen($header) > $this->maxHeaderSize) {
            throw new HttpException(413, true);
        }

        $firstLine = \strstr($header, "\r\n", true);

        if ($firstLine === false) {
            throw new HttpException(400, true);
        }

        $firstLineParts = \sscanf($firstLine, '%s %s HTTP/%s');

        if (!isset($firstLineParts[0], $firstLineParts[1], $firstLineParts[2])) {
            throw new HttpException(400, true);
        }

        $this->method = $firstLineParts[0];
        $this->uri = $firstLineParts[1];
        $this->version = $firstLineParts[2];

        if (!\in_array($this->version, ['1.0', '1.1'])) {
            throw new HttpException(505, true);
        }

        $tok = \strtok($header, "\r\n");
        while ($tok !== false) {
            if (\str_contains($tok, ':')) {
                [$key, $value] = \explode(':', $tok, 2);
                $value = \trim($value);
                $this->headers[$key] = isset($this->headers[$key]) ? "{$this->headers[$key]},$value" : $value;
            }
            $tok = \strtok("\r\n");
        }
        \parse_str(\preg_replace('/;\s?/', '&', $this->headers['Cookie'] ?? ''), $this->cookie);
        $this->contentLength = (int) ($this->headers['Content-Length'] ?? '0');
        $this->headersParsed = true;
    }

    public function isCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function getPsrServerRequest(): ServerRequestInterface
    {
        if (!$this->isCompleted()) {
            throw new \LogicException('ServerRequest cannot be created until request is complete');
        }

        $psrRequest = new ServerRequest(
            method: $this->method,
            uri: $this->uri,
            serverParams: $_SERVER + [
                'REMOTE_ADDR' => '127.0.0.2',
            ],
        );

        foreach ($this->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        return $psrRequest
            ->withProtocolVersion($this->version)
            ->withCookieParams($this->cookie)
            //->withParsedBody($workermanRequest->post())
            //->withUploadedFiles($this->normalizeFiles($workermanRequest->file()))
            ->withBody(Stream::create($this->body))
            ;
    }





    /**
    protected function parsePost(): void
    {
        static $cache = [];
        $this->data['post'] = $this->data['files'] = [];
        $contentType = $this->header('content-type', '');
        if (preg_match('/boundary="?(\S+)"?/', $contentType, $match)) {
            $httpPostBoundary = '--' . $match[1];
            $this->parseUploadFiles($httpPostBoundary);
            return;
        }
        $bodyBuffer = $this->rawBody();
        if ($bodyBuffer === '') {
            return;
        }
        if (preg_match('/\bjson\b/i', $contentType)) {
            $this->data['post'] = (array)json_decode($bodyBuffer, true);
        } else {
            parse_str($bodyBuffer, $this->data['post']);
        }
    }

    protected function parseUploadFiles(string $httpPostBoundary): void
    {
        $httpPostBoundary = trim($httpPostBoundary, '"');
        $buffer = $this->buffer;
        $postEncodeString = '';
        $filesEncodeString = '';
        $files = [];
        $bodyPosition = strpos($buffer, "\r\n\r\n") + 4;
        $offset = $bodyPosition + strlen($httpPostBoundary) + 2;
        $maxCount = static::$maxFileUploads;
        while ($maxCount-- > 0 && $offset) {
            $offset = $this->parseUploadFile($httpPostBoundary, $offset, $postEncodeString, $filesEncodeString, $files);
        }
        if ($postEncodeString) {
            parse_str($postEncodeString, $this->data['post']);
        }

        if ($filesEncodeString) {
            parse_str($filesEncodeString, $this->data['files']);
            array_walk_recursive($this->data['files'], function (&$value) use ($files) {
                $value = $files[$value];
            });
        }
    }

    protected function parseUploadFile(string $boundary, int $sectionStartOffset, string &$postEncodeString, string &$filesEncodeStr, array &$files): int
    {
        $file = [];
        $boundary = "\r\n$boundary";
        if (strlen($this->buffer) < $sectionStartOffset) {
            return 0;
        }
        $sectionEndOffset = strpos($this->buffer, $boundary, $sectionStartOffset);
        if (!$sectionEndOffset) {
            return 0;
        }
        $contentLinesEndOffset = strpos($this->buffer, "\r\n\r\n", $sectionStartOffset);
        if (!$contentLinesEndOffset || $contentLinesEndOffset + 4 > $sectionEndOffset) {
            return 0;
        }
        $contentLinesStr = substr($this->buffer, $sectionStartOffset, $contentLinesEndOffset - $sectionStartOffset);
        $contentLines = explode("\r\n", trim($contentLinesStr . "\r\n"));
        $boundaryValue = substr($this->buffer, $contentLinesEndOffset + 4, $sectionEndOffset - $contentLinesEndOffset - 4);
        $uploadKey = false;
        foreach ($contentLines as $contentLine) {
            if (!strpos($contentLine, ': ')) {
                return 0;
            }
            [$key, $value] = explode(': ', $contentLine);
            switch (strtolower($key)) {

                case "content-disposition":
                    // Is file data.
                    if (preg_match('/name="(.*?)"; filename="(.*?)"/i', $value, $match)) {
                        $error = 0;
                        $tmpFile = '';
                        $fileName = $match[1];
                        $size = strlen($boundaryValue);
                        $tmpUploadDir = HTTP::uploadTmpDir();
                        if (!$tmpUploadDir) {
                            $error = UPLOAD_ERR_NO_TMP_DIR;
                        } else if ($boundaryValue === '' && $fileName === '') {
                            $error = UPLOAD_ERR_NO_FILE;
                        } else {
                            $tmpFile = tempnam($tmpUploadDir, 'workerman.upload.');
                            if ($tmpFile === false || false === file_put_contents($tmpFile, $boundaryValue)) {
                                $error = UPLOAD_ERR_CANT_WRITE;
                            }
                        }
                        $uploadKey = $fileName;
                        // Parse upload files.
                        $file = [...$file, 'name' => $match[2], 'tmp_name' => $tmpFile, 'size' => $size, 'error' => $error, 'full_path' => $match[2]];
                        $file['type'] ??= '';
                        break;
                    }
                    // Is post field.
                    // Parse $POST.
                    if (preg_match('/name="(.*?)"$/', $value, $match)) {
                        $k = $match[1];
                        $postEncodeString .= urlencode($k) . "=" . urlencode($boundaryValue) . '&';
                    }
                    return $sectionEndOffset + strlen($boundary) + 2;
                
                case "content-type":
                    $file['type'] = trim($value);
                    break;

                case "webkitrelativepath":
                    $file['full_path'] = \trim($value);
                    break;
            }
        }
        if ($uploadKey === false) {
            return 0;
        }
        $filesEncodeStr .= urlencode($uploadKey) . '=' . count($files) . '&';
        $files[] = $file;

        return $sectionEndOffset + strlen($boundary) + 2;
    }
    */

//    public function __destruct()
//    {
//        if (isset($this->data['files'])) {
//            clearstatcache();
//            array_walk_recursive($this->data['files'], function ($value, $key) {
//                if ($key === 'tmp_name' && is_file($value)) {
//                    unlink($value);
//                }
//            });
//        }
//    }
}
