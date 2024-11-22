<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\ObserveSummaryMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Metric;
use Revolt\EventLoop;

final class Summary extends Metric
{
    protected const TYPE = 'summary';

    private array $buffer = [];

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function observe(float $value, array $labels = []): void
    {
        $this->checkLabels($labels);

        $key = \hash('xxh128', \json_encode($labels));
        $this->buffer[$key] ??= [[], ''];
        $buffer = &$this->buffer[$key][0];
        $callbackId = &$this->buffer[$key][1];
        $buffer[] = $value;

        if ($callbackId !== '') {
            return;
        }

        $callbackId = EventLoop::delay(self::FLUSH_TIMEOUT, function() use($labels, &$buffer, $key) {
            $values = $buffer;
            unset($this->buffer[$key]);
            $this->messageBus->dispatch(new ObserveSummaryMessage($this->namespace, $this->name, $labels, $values));
        });
    }

    /**
     * Creates default quantiles.
     *
     * @return list<float<0, 1>>
     */
    public static function getDefaultQuantiles(): array
    {
        return [0.01, 0.05, 0.5, 0.95, 0.99];
    }
}
