<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Supervisor\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class ProcessSetOptionsEvent implements MessageInterface
{
    public function __construct(
        public int $pid,
        public bool $reloadable,
    ) {
    }
}
