<?php

declare(strict_types=1);

namespace PHPStreamServer\MetricsPlugin\Internal\Message;

use PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<null>
 */
final readonly class ObserveHistorgamMessage implements MessageInterface
{
    /**
     * @param array<string, string> $labels
     * @param list<float> $values
     */
    public function __construct(
        public string $namespace,
        public string $name,
        public array $labels,
        public array $values,
    ) {
    }
}
