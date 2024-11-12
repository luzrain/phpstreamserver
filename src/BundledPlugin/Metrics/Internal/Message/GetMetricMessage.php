<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<GetMetricResponse|false>
 */
final readonly class GetMetricMessage implements MessageInterface
{
    public const TYPE_COUNTER = 'counter';
    public const TYPE_GAUGE = 'gauge';
    public const TYPE_HISTOGRAM = 'histogram';
    public const TYPE_SUMMARY = 'summary';

    private function __construct(
        public string $type,
        public string $namespace,
        public string $name,
    ) {
    }

    public static function counter(string $namespace, string $name): self
    {
        return new self(self::TYPE_COUNTER, $namespace, $name);
    }

    public static function gauge(string $namespace, string $name): self
    {
        return new self(self::TYPE_GAUGE, $namespace, $name);
    }

    public static function histogram(string $namespace, string $name): self
    {
        return new self(self::TYPE_HISTOGRAM, $namespace, $name);
    }

    public static function summary(string $namespace, string $name): self
    {
        return new self(self::TYPE_SUMMARY, $namespace, $name);
    }
}
