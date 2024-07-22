<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\WritableStream;
use Amp\Future;
use function Amp\async;

final class SocketPairMessageBus implements MessageBus
{
    private WritableStream $stream;

    /**
     * @param resource $resource
     */
    public function __construct(mixed $resource)
    {
        $this->stream = new WritableResourceStream($resource);
    }

    public function dispatch(Message $message): Future
    {
        $payload = \serialize($message);
        $stream = &$this->stream;

        return async(static function () use ($payload, &$stream): null {
            $stream->write($payload . "\r\n");

            return null;
        });
    }
}
