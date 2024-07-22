<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\ByteStream\ReadableResourceStream;
use function Amp\async;

final class SocketPairMessageHandler implements MessageHandler
{
    public const CHUNK_SIZE = 1048576;

    private array $subscribers = [];

    /**
     * @param resource $resource
     */
    public function __construct(mixed $resource)
    {
        \stream_set_blocking($resource, false);
        \stream_set_read_buffer($resource, 0);

        $stream = new ReadableResourceStream($resource);
        $subscribers = &$this->subscribers;
        $buffer = '';

        async(static function () use ($stream, &$subscribers, &$buffer) {
            while (($chunk = $stream->read(null, self::CHUNK_SIZE)) !== null) {
                $buffer .= $chunk;
                if (\str_ends_with($buffer, "\r\n")) {
                    $tok = strtok($buffer, "\r\n");
                    while ($tok !== false) {

                        $message = \unserialize($tok);
                        \assert($message instanceof Message);

                        foreach ($subscribers[$message::class] ?? [] as $subscriber) {
                            $subscriber($message);
                        }

                        $tok = strtok("\r\n");
                    }
                    $buffer = '';
                }
            }
        });
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): void $closure
     */
    public function subscribe(string $class, \Closure $closure): void
    {
        $this->subscribers[$class][\spl_object_id($closure)] = $closure;
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): void $closure
     */
    public function unsubscribe(string $class, \Closure $closure): void
    {
        unset($this->subscribers[$class][\spl_object_id($closure)]);
    }
}
