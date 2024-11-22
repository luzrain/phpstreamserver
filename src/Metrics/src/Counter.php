<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\IncreaseCounterMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Metric;
use Revolt\EventLoop;

final class Counter extends Metric
{
    protected const TYPE = 'counter';

    private array $buffer = [];

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function inc(array $labels = []): void
    {
        $this->add(1, $labels);
    }

    /**
     * @param array<string, string> $labels
     * @throws LabelsNotMatchException
     */
    public function add(int $value, array $labels = []): void
    {
        $this->checkLabels($labels);

        $key = \hash('xxh128', \json_encode($labels));
        $this->buffer[$key] ??= [0, ''];
        $buffer = &$this->buffer[$key][0];
        $callbackId = &$this->buffer[$key][1];
        $buffer += $value;

        if ($callbackId !== '') {
            return;
        }

        $callbackId = EventLoop::delay(self::FLUSH_TIMEOUT, function() use($labels, &$buffer, $key) {
            $value = $buffer;
            unset($this->buffer[$key]);
            $this->messageBus->dispatch(new IncreaseCounterMessage($this->namespace, $this->name, $labels, $value));
        });
    }
}
