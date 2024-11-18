<?php

declare(strict_types=1);

namespace PHPStreamServer\MessageBus;

use Amp\Future;

interface MessageBusInterface
{
    /**
     * @template T
     * @param MessageInterface<T> $message
     * @return Future<T>
     */
    public function dispatch(MessageInterface $message): Future;
}
