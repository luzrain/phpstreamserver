<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics\Exception;

final class MetricNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $type, string $namespace, string $name)
    {
        parent::__construct(\sprintf('%s metric "%s_%s" not found', \ucfirst($type), $namespace, $name));
    }
}
