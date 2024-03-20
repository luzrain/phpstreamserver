<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Protocols;

use Luzrain\PhpRunner\Exception\EncodeTypeError;
use Luzrain\PhpRunner\Exception\HttpException;
use Luzrain\PhpRunner\Exception\TlsHandshakeException;
use Luzrain\PhpRunner\Server;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;
use Luzrain\PhpRunner\Server\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @implements ProtocolInterface<ServerRequestInterface, ResponseInterface>
 */
final class Http implements ProtocolInterface
{
    private const MAX_HEADER_SIZE = 32768;

    private Request|null $request = null;
    private ServerRequestInterface|null $psrRequest = null;

    public function __construct(
        /**
         * The maximum allowed size of the client http request body in bytes.
         * If the size in a request exceeds the value, the 413 (Request Entity Too Large) error is returned to the client.
         * Setting size to 0 disables checking of client request body size.
         */
        private readonly int $maxBodySize = 0,
    ) {
    }

    /**
     * @throws HttpException
     */
    public function decode(ConnectionInterface $connection, string $buffer): ServerRequestInterface|null
    {
        $this->request ??= new Request(
            maxHeaderSize: self::MAX_HEADER_SIZE,
            maxBodySize: $this->maxBodySize,
        );

        try {
            $this->request->parse($buffer);
            if ($this->request->isCompleted()) {
                $this->psrRequest = $this->request->getPsrServerRequest(
                    serverAddr: $connection->getLocalIp(),
                    serverPort: $connection->getLocalPort(),
                    remoteAddr: $connection->getRemoteIp(),
                    remotePort: $connection->getRemotePort(),
                );
                return $this->psrRequest;
            }
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(400, true, $e);
        }

        return null;
    }

    /**
     * @return \Generator<string>
     * @throws EncodeTypeError
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator
    {
        if (!$response instanceof ResponseInterface) {
            throw new EncodeTypeError(ResponseInterface::class, \get_debug_type($response));
        }

        $version = $this->psrRequest?->getProtocolVersion() ?? $response->getProtocolVersion();
        $msg = 'HTTP/' . $version . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . "\r\n";
        if ($this->shouldClose()) {
            $response = $response->withHeader('Connection', 'close');
        } elseif(!$response->hasHeader('Connection') && $this->psrRequest !== null && $this->psrRequest->hasHeader('Connection')) {
            $response = $response->withHeader('Connection', $this->psrRequest->getHeaderLine('Connection'));
        }
        if (!$response->hasHeader('Server')) {
            $response = $response->withHeader('Server', Server::VERSION_STRING);
        }
        if (!$response->hasHeader('Date')) {
            $response = $response->withHeader('Date', \date(\DateTimeInterface::RFC7231));
        }
        if (!$response->hasHeader('Content-Type')) {
            $response = $response->withHeader('Content-Type', 'text/html');
        }
        if (!$response->hasHeader('Transfer-Encoding') && !$response->getHeaderLine('Content-Length')) {
            $response = $response->withHeader('Content-Length', (string) ($response->getBody()->getSize() ?? 0));
        }
        foreach ($response->getHeaders() as $name => $values) {
            $msg .= "$name: " . \implode(', ', $values) . "\r\n";
        }
        $msg .= "\r\n";

        if (($response->getBody()->getSize() ?? 0) <= $connection::WRITE_BUFFER_SIZE) {
            $response->getBody()->rewind();
            $msg .= $response->getBody()->getContents();
            yield $msg;
        } else {
            yield $msg;
            $response->getBody()->rewind();
            while (!$response->getBody()->eof()) {
                yield $response->getBody()->read($connection::WRITE_BUFFER_SIZE);
            }
            $response->getBody()->close();
        }

        if ($response->getHeaderLine('Connection') === 'close') {
            $connection->close();
        }

        $this->request = null;
        $this->psrRequest = null;
    }

    /**
     * @throws \Throwable
     */
    public function onException(ConnectionInterface $connection, \Throwable $e): void
    {
        if ($e instanceof HttpException) {
            $httpRequestException = $e;
        } elseif ($e instanceof TlsHandshakeException) {
            $httpRequestException = new HttpException(400, true, $e);
        } else {
            $httpRequestException = new HttpException(500, $this->shouldClose(), $e);
        }

        $connection->send($httpRequestException->getResponse());
        $this->request = null;
        $this->psrRequest = null;

        if (!$e instanceof HttpException) {
            throw $e;
        }
    }

    private function shouldClose(): bool
    {
        if ($this->psrRequest !== null) {
            return $this->psrRequest->getHeaderLine('Connection') === 'close' || $this->psrRequest->getProtocolVersion() === '1.0';
        } else {
            return true;
        }
    }
}
