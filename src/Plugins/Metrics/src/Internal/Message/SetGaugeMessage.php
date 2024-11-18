<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class SetGaugeMessage implements MessageInterface
{
    public function __construct(
        public string $namespace,
        public string $name,
        public array $labels,
        public float $value,
        public bool $increase = false,
    ) {
    }
}
