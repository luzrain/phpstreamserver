<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Revolt\EventLoop;

final class InterprocessPipe
{
    private const READ_BUFFER = 65536;

    /**
     * @var array<\Closure>
     */
    private array $subscribers = [];

    private string $readBuffer = '';
    private string $writeBuffer = '';
    private string $onReadableCallbackId;
    private string $onWritableCallbackId = '';

    /**
     * @param resource $rxPipe
     * @param resource $txPipe
     */
    public function __construct(private mixed $rxPipe, private mixed $txPipe = null)
    {
        $this->txPipe ??= $this->rxPipe;
        \stream_set_blocking($this->rxPipe, false);
        \stream_set_blocking($this->txPipe, false);
        \stream_set_read_buffer($this->rxPipe, 0);
        \stream_set_write_buffer($this->txPipe, 0);

        $this->onReadableCallbackId = EventLoop::onReadable($this->rxPipe, function () {
            foreach ($this->read() ?? [] as $payload) {
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
        while (false !== $chunk = \stream_get_line($this->rxPipe, self::READ_BUFFER, "\r\n")) {
            if (\str_ends_with($chunk, 'END')) {
                yield \substr($this->readBuffer . $chunk, 0, -3);
                $this->readBuffer = '';
            } else {
                $this->readBuffer .= $chunk;
            }
        }
    }

    /**
     * @param string $bytes
     */
    private function write(string $bytes): void
    {
        if ($this->writeBuffer !== '') {
            $this->writeBuffer .= $bytes;

            return;
        }

        $length = \strlen($bytes);
        $written = (int) \fwrite($this->txPipe, $bytes);

        if ($length === $written) {
            return;
        }

        if ($this->onWritableCallbackId === '') {
            $writeBuffer = &$this->writeBuffer;
            $this->onWritableCallbackId = EventLoop::disable(EventLoop::onWritable(
                $this->txPipe,
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
        EventLoop::enable($this->onWritableCallbackId);
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

    public function publish(object $message): void
    {
        $this->write(\serialize($message) . "END\r\n");
    }

    public function free(): void
    {
        EventLoop::cancel($this->onReadableCallbackId);
        EventLoop::cancel($this->onWritableCallbackId);
    }
}
