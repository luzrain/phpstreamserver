<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Server\Protocols;

use Luzrain\PHPStreamServer\Exception\EncodeTypeError;
use Luzrain\PHPStreamServer\Exception\TooLargePayload;
use Luzrain\PHPStreamServer\Internal\EventEmitter\EventEmitterTrait;
use Luzrain\PHPStreamServer\Server\Connection\ConnectionInterface;

final class Text implements ProtocolInterface
{
    use EventEmitterTrait;

    /** @var \WeakMap<ConnectionInterface, string> */
    private \WeakMap $buffer;

    public function __construct(
        private readonly int $maxBodySize = 1048576, // 1 MB
    ) {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->buffer = new \WeakMap();
    }

    public function handle(ConnectionInterface $connection): void
    {
        $connection->on($connection::EVENT_ERROR, $this->handleException(...));
        $connection->on($connection::EVENT_DATA, function (string $buffer) use (&$connection) {
            $this->buffer[$connection] ??= '';
            $this->buffer[$connection] .= $buffer;

            if (\strlen($this->buffer[$connection]) > $this->maxBodySize) {
                $this->buffer->offsetUnset($connection);
                $this->handleException($connection, new TooLargePayload($this->maxBodySize));
            }

            if (\str_ends_with($buffer, "\n")) {
                $this->emit(self::EVENT_MESSAGE, $connection, \rtrim($this->buffer[$connection]));
                $this->buffer->offsetUnset($connection);
            }
        });
    }

    /**
     * @return \Generator<string>
     */
    public function encode(ConnectionInterface $connection, mixed $response): \Generator
    {
        if (!\is_string($response)) {
            $this->handleException($connection, new EncodeTypeError('string', \get_debug_type($response)));
        }

        yield $response . "\n";
    }

    /**
     * @throws \Throwable
     */
    private function handleException(ConnectionInterface $connection, \Throwable $e): void
    {
        if ($e instanceof TooLargePayload) {
            $connection->close();
        }

        throw $e;
    }
}
