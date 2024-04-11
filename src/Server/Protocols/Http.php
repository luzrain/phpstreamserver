<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Protocols;

use Luzrain\PHPStreamServer\Exception\EncodeTypeError;
use Luzrain\PHPStreamServer\Exception\HttpException;
use Luzrain\PHPStreamServer\Exception\TlsHandshakeException;
use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterTrait;
use Luzrain\PHPStreamServer\Server;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;
use Luzrain\PHPStreamServer\Server\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Http implements ProtocolInterface
{
    use EventEmitterTrait;

    private const MAX_HEADER_SIZE = 32768;

    /** @var \WeakMap<ConnectionInterface, Request> */
    private \WeakMap $parserByConnection;

    /** @var \WeakMap<ConnectionInterface, ServerRequestInterface> */
    private \WeakMap $requestByConnection;

    public function __construct(
        /**
         * The maximum allowed size of the client http request body in bytes.
         * If the size in a request exceeds the value, the 413 (Request Entity Too Large) error is returned to the client.
         * Setting size to 0 disables checking of client request body size.
         */
        private readonly int $maxBodySize = 0,
    ) {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->parserByConnection = new \WeakMap();
        /** @psalm-suppress PropertyTypeCoercion */
        $this->requestByConnection = new \WeakMap();
    }

    public function handle(ConnectionInterface $connection): void
    {
        $connection->on($connection::EVENT_ERROR, $this->handleException(...));
        $connection->on($connection::EVENT_DATA, function (string $buffer) use (&$connection) {
            $this->parserByConnection[$connection] ??= new Request(
                maxHeaderSize: self::MAX_HEADER_SIZE,
                maxBodySize: $this->maxBodySize,
            );

            try {
                $this->parserByConnection[$connection]->parse($buffer);
                if ($this->parserByConnection[$connection]->isCompleted()) {
                    $psrRequest = $this->parserByConnection[$connection]->getPsrServerRequest(
                        serverAddr: $connection->getLocalIp(),
                        serverPort: $connection->getLocalPort(),
                        remoteAddr: $connection->getRemoteIp(),
                        remotePort: $connection->getRemotePort(),
                    );

                    $this->requestByConnection[$connection] = $psrRequest;
                    $this->parserByConnection->offsetUnset($connection);
                    $this->emit(self::EVENT_MESSAGE, $connection, $psrRequest);
                }
            } catch (\InvalidArgumentException $e) {
                $this->handleException($connection, new HttpException(400, true, $e));
            } catch (\Throwable $e) {
                $this->handleException($connection, $e);
            }
        });
    }

    /**
     * @return \Generator<string>
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator
    {
        if (!$response instanceof ResponseInterface) {
            $this->handleException($connection, new EncodeTypeError(ResponseInterface::class, \get_debug_type($response)));
        }

        $version = $this->requestByConnection->offsetExists($connection)
            ? $this->requestByConnection[$connection]->getProtocolVersion()
            : $response->getProtocolVersion();

        $msg = 'HTTP/' . $version . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . "\r\n";
        if ($this->shouldClose($connection)) {
            $response = $response->withHeader('Connection', 'close');
        } elseif(!$response->hasHeader('Connection')
            && $this->requestByConnection->offsetExists($connection)
            && $this->requestByConnection[$connection]->hasHeader('Connection') === true
        ) {
            $response = $response->withHeader('Connection', $this->requestByConnection[$connection]->getHeaderLine('Connection'));
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

        $this->parserByConnection->offsetUnset($connection);
        $this->requestByConnection->offsetUnset($connection);
    }

    /**
     * @throws \Throwable
     */
    private function handleException(ConnectionInterface $connection, \Throwable $e): void
    {
        if ($e instanceof HttpException) {
            $httpRequestException = $e;
        } elseif ($e instanceof TlsHandshakeException) {
            $httpRequestException = new HttpException(400, true, $e);
        } else {
            $httpRequestException = new HttpException(500, $this->shouldClose($connection), $e);
        }

        $connection->send($httpRequestException->getResponse());

        $this->parserByConnection->offsetUnset($connection);
        $this->requestByConnection->offsetUnset($connection);

        if ($e instanceof HttpException) {
            return;
        }

        throw $e;
    }

    private function shouldClose(ConnectionInterface $connection): bool
    {
        if (isset($this->requestByConnection[$connection])) {
            return $this->requestByConnection[$connection]->getHeaderLine('Connection') === 'close'
                || $this->requestByConnection[$connection]->getProtocolVersion() === '1.0';
        }

        return true;
    }
}
