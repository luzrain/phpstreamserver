<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Event;

use Luzrain\PHPStreamServer\Internal\MessageBus\Message;

/**
 * @implements Message<void>
 */
final readonly class ProcessSetOptionsEvent implements Message
{
    public function __construct(
        public int $pid,
        public bool $reloadable,
    ) {
    }
}
