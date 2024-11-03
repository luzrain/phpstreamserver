<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\MessageBus;

use Amp\Future;

interface MessageBus
{
    /**
     * @template T
     * @param Message<T> $message
     * @return Future<T>
     */
    public function dispatch(Message $message): Future;
}
