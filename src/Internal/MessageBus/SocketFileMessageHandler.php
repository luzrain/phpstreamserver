<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use function Amp\async;
use function Amp\Socket\listen;

final class SocketFileMessageHandler implements MessageHandler
{
    public const CHUNK_SIZE = 1048576;

    /**
     * @var list<\Closure>
     */
    private array $subscribers = [];

    public function __construct(string $socketFile)
    {
        $server = listen("unix://$socketFile");
        $subscribers = &$this->subscribers;

        async(static function () use ($server, &$subscribers) {
            while ($socket = $server->accept()) {
                $data = $socket->read(null, self::CHUNK_SIZE);

                $message = \unserialize($data);
                \assert($message instanceof Message);
                $return = null;

                foreach ($subscribers[$message::class] ?? [] as $subscriber) {
                    $subscriberAnswer = $subscriber($message);
                    if ($subscriberAnswer !== null) {
                        $return = $subscriberAnswer;
                        break;
                    }
                }

                $socket->write(\serialize($return));
                $socket->end();
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
