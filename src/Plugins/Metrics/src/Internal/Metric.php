<?php

declare(strict_types=1);

namespace PHPStreamServer\MetricsPlugin\Internal;

use PHPStreamServer\MetricsPlugin\Exception\LabelsNotMatchException;
use PHPStreamServer\MetricsPlugin\Internal\Message\RemoveMetricMessage;
use PHPStreamServer\MessageBus\MessageBusInterface;

abstract class Metric
{
    protected const TYPE = '';
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
     * @param array<string, string> $labels
     */
    public function remove(array $labels = []): void
    {
        $this->checkLabels($labels);
        $this->messageBus->dispatch(new RemoveMetricMessage(static::TYPE, $this->namespace, $this->name, $labels));
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