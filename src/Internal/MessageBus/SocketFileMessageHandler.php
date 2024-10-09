<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\ByteStream\StreamException;
use Amp\Future;
use Amp\Socket\ResourceServerSocket;
use Amp\Socket\ResourceServerSocketFactory;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\weakClosure;

/**
 * @internal
 */
final class SocketFileMessageHandler implements MessageHandler, MessageBus
{
    private ResourceServerSocket $socket;

    /**
     * @var array<class-string, array<int, \Closure>>
     */
    private array $subscribers = [];

    private string $callbackId;

    public function __construct(string $socketFile)
    {
        $this->socket = (new ResourceServerSocketFactory(chunkSize: PHP_INT_MAX))->listen("unix://$socketFile");
        $server = &$this->socket;
        $subscribers = &$this->subscribers;

        $this->callbackId = EventLoop::defer(static function () use (&$server, &$subscribers) {
            while ($socket = $server->accept()) {
                $data = $socket->read(limit: PHP_INT_MAX);

                // if socket is not readable anymore
                if ($data === null) {
                    continue;
                }

                $size = (int) \substr($data, 0, 10);
                $data = \substr($data, 10);

                while (\strlen($data) < $size) {
                    $data .= $socket->read(limit: PHP_INT_MAX);
                }

                $message = \unserialize($data);
                \assert($message instanceof Message);
                $return = null;

                foreach ($subscribers[$message::class] ?? [] as $subscriber) {
                    if (null !== $subscriberReturn = $subscriber($message)) {
                        $return = $subscriberReturn;
                        break;
                    }
                }

                try {
                    $socket->write(\serialize($return));
                } catch (StreamException) {
                    // if socket is not writable anymore
                    continue;
                }

                $socket->end();
            }
        });

        $this->subscribe(CompositeMessage::class, weakClosure(function (CompositeMessage $event) {
            foreach ($event->messages as $message) {
                $this->dispatch($message);
            }
        }));
    }

    public function __destruct()
    {
        EventLoop::cancel($this->callbackId);
        $this->socket->close();
        unset($this->socket);
        unset($this->subscribers);
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): mixed $closure
     */
    public function subscribe(string $class, \Closure $closure): void
    {
        $this->subscribers[$class][\spl_object_id($closure)] = $closure;
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): mixed $closure
     */
    public function unsubscribe(string $class, \Closure $closure): void
    {
        unset($this->subscribers[$class][\spl_object_id($closure)]);
    }

    public function dispatch(Message $message): Future
    {
        $subscribers = &$this->subscribers;

        return async(static function () use (&$subscribers, &$message): mixed {
            foreach ($subscribers[$message::class] ?? [] as $subscriber) {
                if (null !== $subscriberReturn = $subscriber($message)) {
                    return $subscriberReturn;
                }
            }

            return null;
        });
    }
}
