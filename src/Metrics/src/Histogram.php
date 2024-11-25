<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Metrics;

use PHPStreamServer\Plugin\Metrics\Exception\LabelsNotMatchException;
use PHPStreamServer\Plugin\Metrics\Internal\Message\ObserveHistorgamMessage;
use PHPStreamServer\Plugin\Metrics\Internal\Metric;
use Revolt\EventLoop;

final class Histogram extends Metric
{
    protected const TYPE = 'histogram';

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

        $callbackId = EventLoop::delay(self::FLUSH_TIMEOUT, function () use ($labels, &$buffer, $key) {
            $values = $buffer;
            unset($this->buffer[$key]);
            $this->messageBus->dispatch(new ObserveHistorgamMessage($this->namespace, $this->name, $labels, $values));
        });
    }

    /**
     * Creates count buckets, where the lowest bucket has an upper bound of start and each following bucket's upper
     * bound is factor times the previous bucket's upper bound.
     * The returned slice is meant to be used for the Buckets field of HistogramOpts.
     *
     * @return list<float>
     */
    public static function exponentialBuckets(float $start, float $factor, int $count): array
    {
        $start > 0 ?: throw new \InvalidArgumentException('$start must be a positive integer');
        $factor > 0 ?: throw new \InvalidArgumentException('$factor must greater than 1');
        $count >= 1 ?: throw new \InvalidArgumentException('$count must be a positive integer');

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $buckets[] = $start;
            $start *= $factor;
        }

        return $buckets;
    }

    /**
     * Creates count regular buckets, each width wide, where the lowest bucket has an upper bound of start.
     * The returned slice is meant to be used for the Buckets field of HistogramOpts.
     *
     * @return list<float>
     */
    public static function linearBuckets(float $start, float $width, int $count): array
    {
        $width > 0 ?: throw new \InvalidArgumentException('$width must greater than 1');
        $count >= 1 ?: throw new \InvalidArgumentException('$count must be a positive integer');

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $buckets[] = $start;
            $start += $width;
        }

        return $buckets;
    }

    /**
     * Creates default buckets.
     *
     * @return list<float>
     */
    public static function defaultBuckets(): array
    {
        return [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];
    }
}
