<?php

declare(strict_types=1);

namespace PHPStreamServer\MetricsPlugin\Internal\Message;

final readonly class GetMetricResponse
{
    public function __construct(
        public string $type,
        public string $namespace,
        public string $name,
        public string $help,
        public array $labels,
    ) {
    }
}
