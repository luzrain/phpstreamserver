<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal;

use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Exception\LabelsNotMatchException;
use Luzrain\PHPStreamServer\MessageBus\MessageBusInterface;

abstract class Metric
{
    protected const FLUSH_TIMEOUT = 0.2;

    private int $labelsCount;

    public function __construct(
        protected readonly MessageBusInterface $messageBus,
        protected readonly string $namespace,
        protected readonly string $name,
        protected readonly string $help,
        protected readonly array $labels = [],
    ) {
        $this->labelsCount = \count($this->labels);
    }

    /**
     * @throws LabelsNotMatchException
     */
    protected final function checkLabels(array $labels = []): void
    {
        $assignedLabels = \array_keys($labels);
        if ($this->labelsCount !== \count($assignedLabels) || \array_diff($this->labels, $assignedLabels) !== []) {
            throw new LabelsNotMatchException($this->labels, $assignedLabels);
        }
    }
}
