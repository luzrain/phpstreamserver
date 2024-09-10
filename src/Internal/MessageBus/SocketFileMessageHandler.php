<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\ByteStream\StreamException;
use Amp\Socket\ResourceServerSocket;
use Amp\Socket\ResourceServerSocketFactory;
use Revolt\EventLoop;

final class SocketFileMessageHandler implements MessageHandler
{
    private ResourceServerSocket $socket;

    /**
     * @var array<class-string, array<int, \Closure>>
     */
    private array $subscribers = [];

    public function __construct(string $socketFile)
    {
        $this->socket = (new ResourceServerSocketFactory(chunkSize: PHP_INT_MAX))->listen("unix://$socketFile");
        $server = &$this->socket;
        $subscribers = &$this->subscribers;

        EventLoop::queue(static function () use (&$server, &$subscribers) {
            while ($socket = $server->accept()) {
                $data = $socket->read(limit: PHP_INT_MAX);

                // if socket is not readable anymore
                if ($data === null) {
                    continue;
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
    }

    public function __destruct()
    {
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
}
