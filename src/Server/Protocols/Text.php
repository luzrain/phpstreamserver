<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Server\Protocols;

use Luzrain\PhpRunner\Exception\EncodeTypeError;
use Luzrain\PhpRunner\Exception\TooLargePayload;
use Luzrain\PhpRunner\Server\Connection\ConnectionInterface;

/**
 * @implements ProtocolInterface<string, string>
 */
final class Text implements ProtocolInterface
{
    private string $buffer = '';

    public function __construct(
        private readonly int $maxBodySize = 1048576, // 1 MB
    ) {
    }

    /**
     * @throws TooLargePayload
     */
    public function decode(ConnectionInterface $connection, string $buffer): string|null
    {
        $this->buffer .= $buffer;

        if (\strlen($this->buffer) > $this->maxBodySize) {
            $this->buffer = '';
            throw new TooLargePayload($this->maxBodySize);
        }

        if (\str_ends_with($this->buffer, "\n")) {
            try {
                return \rtrim($this->buffer);
            } finally {
                $this->buffer = '';
            }
        }

        return null;
    }

    /**
     * @param string $response
     * @return \Generator<string>
     * @throws EncodeTypeError
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator
    {
        if (!\is_string($response)) {
            throw new EncodeTypeError('string', \get_debug_type($response));
        }

        yield $response . "\n";
    }

    /**
     * @throws \Throwable
     */
    public function onException(ConnectionInterface $connection, \Throwable $e): void
    {
        if ($e instanceof TooLargePayload) {
            $connection->close();
        }

        throw $e;
    }
}
