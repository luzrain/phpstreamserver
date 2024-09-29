<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\MessageBus;

use Amp\Future;
use Luzrain\PHPStreamServer\Message;

/**
 * @internal
 */
interface MessageBus
{
    /**
     * @template T
     * @param Message<T> $message
     * @return Future<T>
     */
    public function dispatch(Message $message): Future;
}
