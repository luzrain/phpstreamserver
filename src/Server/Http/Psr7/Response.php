<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Http\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response implements ResponseInterface
{
    use MessageTrait;

    private const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    private int $statusCode;
    private string $reasonPhrase;

    /**
     * @param string|resource|StreamInterface $body Response body
     * @param int $code $code Status code
     * @param array $headers Response headers
     * @param string $version Protocol version
     * @param string $reasonPhrase Reason phrase (when empty a default will be used based on the status code)
     */
    public function __construct(mixed $body = '', int $code = 200, array $headers = [], string $version = '1.1', string $reasonPhrase = '')
    {
        $this->stream = match (true) {
            \is_string($body) => new StringStream($body),
            \is_resource($body) => new ResourceStream($body),
            $body instanceof StreamInterface => $body,
            default => throw new \InvalidArgumentException(\sprintf('%s::__construct(): Argument #1 ($body) must be of type string|resource|StreamInterface, %s given', self::class, \get_debug_type($body))),
        };

        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException(\sprintf('%s::__construct(): Argument #2 ($code) has to be between 100 and 599, %s given', self::class, $code));
        }

        $this->statusCode = $code;
        $this->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : self::PHRASES[$code] ?? '';
        $this->protocol = $version;
        $this->setHeaders($headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        if ($code < 100 || $code > 599) {
            throw new \InvalidArgumentException(\sprintf('%s::withStatus(): Argument #1 ($code) has to be between 100 and 599, %s given', self::class, $code));
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : self::PHRASES[$code] ?? '';

        return $new;
    }
}
