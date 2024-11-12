<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message;

use Luzrain\PHPStreamServer\MessageBus\MessageInterface;

/**
 * @implements MessageInterface<bool>
 */
final readonly class RegisterMetricMessage implements MessageInterface
{
    public const TYPE_COUNTER = 'counter';
    public const TYPE_GAUGE = 'gauge';
    public const TYPE_HISTOGRAM = 'histogram';
    public const TYPE_SUMMARY = 'summary';

    private function __construct(
        public string $type,
        public string $namespace,
        public string $name,
        public string $help,
        public array $labels,
        public array|null $buckets = null,
    ) {
    }

    public static function counter(string $namespace, string $name, string $help, array $labels): self
    {
        return new self(self::TYPE_COUNTER, $namespace, $name, $help, $labels);
    }

    public static function gauge(string $namespace, string $name, string $help, array $labels): self
    {
        return new self(self::TYPE_GAUGE, $namespace, $name, $help, $labels);
    }

    public static function histogram(string $namespace, string $name, string $help, array $labels, array $buckets): self
    {
        return new self(self::TYPE_HISTOGRAM, $namespace, $name, $help, $labels, $buckets);
    }

    public static function summary(string $namespace, string $name, string $help, array $labels, array|null $buckets = null): self
    {
        return new self(self::TYPE_SUMMARY, $namespace, $name, $help, $labels, $buckets);
    }
}
