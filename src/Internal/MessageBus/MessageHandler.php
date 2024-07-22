<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

interface MessageHandler
{
    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): void $closure
     */
    public function subscribe(string $class, \Closure $closure): void;

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @param \Closure(T): void $closure
     */
    public function unsubscribe(string $class, \Closure $closure): void;
}
