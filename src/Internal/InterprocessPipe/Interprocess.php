<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\InterprocessPipe;

use Revolt\EventLoop;

final class Interprocess
{
    private const READ_BUFFER = 65536;

    /**
     * @var resource
     */
    private mixed $readerResource;

    /**
     * @var resource
     */
    private mixed $writerResource;

    /**
     * @var array<\Closure>
     */
    private array $subscribers = [];

    private string $readBuffer = '';

    private string $writeBuffer = '';

    private string $callbackId;

    public function __construct()
    {
        [$this->readerResource, $this->writerResource] = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        \stream_set_blocking($this->readerResource, false);
        \stream_set_blocking($this->writerResource, false);
        \stream_set_read_buffer($this->readerResource, 0);
        \stream_set_write_buffer($this->writerResource, 0);

        EventLoop::onReadable($this->readerResource, function () {
            foreach ($this->read() as $payload) {
                /** @var object $message */
                $message = \unserialize($payload);
                foreach ($this->subscribers[$message::class] ?? [] as $subscriber) {
                    $subscriber($message);
                }
            }
        });
    }

    /**
     * @return \Generator<object>
     */
    private function read(): \Generator
    {
        while (false !== $chunk = \stream_get_line($this->readerResource, self::READ_BUFFER, "\r\n")) {
            if (\str_ends_with($chunk, 'END')) {
                yield \substr($this->readBuffer . $chunk, 0, -3);
                $this->readBuffer = '';
            } else {
                $this->readBuffer .= $chunk;
            }
        }
    }

    /**
     * @param non-empty-string $bytes
     */
    private function write(string $bytes): void
    {
        if ($this->writeBuffer !== '') {
            $this->writeBuffer .= $bytes;

            return;
        }

        $length = \strlen($bytes);
        $written = (int) \fwrite($this->writerResource, $bytes);

        if ($length === $written) {
            return;
        }

        if (!isset($this->callbackId)) {
            $writeBuffer = &$this->writeBuffer;
            $this->callbackId = EventLoop::disable(EventLoop::onWritable(
                $this->writerResource,
                static function ($callbackId, $writeResource) use (&$writeBuffer) {
                    $written = (int) \fwrite($writeResource, $writeBuffer);
                    $writeBuffer = \substr($writeBuffer, $written);
                    if ($writeBuffer === '') {
                        EventLoop::disable($callbackId);
                    }
                },
            ));
        }

        $this->writeBuffer = \substr($bytes, $written);
        EventLoop::enable($this->callbackId);
    }

    /**
     * @return \Closure(object): void
     */
    public function createPublisherForWorkerProcess(): \Closure
    {
        $this->subscribers = [];

        return function (object $message): void {
            $this->write(\serialize($message) . "END\r\n");
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param \Closure(T): void $closure
     */
    public function subscribe(string $class, \Closure $closure): void
    {
        $this->subscribers[$class][] = $closure;
    }
}
