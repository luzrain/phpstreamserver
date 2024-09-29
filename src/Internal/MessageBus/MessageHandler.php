<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Luzrain\PHPStreamServer\Message;

/**
 * @internal
 */
interface MessageHandler
{
    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): mixed $closure
     */
    public function subscribe(string $class, \Closure $closure): void;

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): mixed $closure
     */
    public function unsubscribe(string $class, \Closure $closure): void;
}
