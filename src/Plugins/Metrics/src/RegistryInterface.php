<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Metrics;

use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Exception\MetricNotFoundException;

interface RegistryInterface
{
    /**
     * @param list<string> $labels
     */
    public function registerCounter(string $namespace, string $name, string $help, array $labels = []): Counter;

    /**
     * @throws MetricNotFoundException
     */
    public function getCounter(string $namespace, string $name): Counter;

    /**
     * @param list<string> $labels
     */
    public function registerGauge(string $namespace, string $name, string $help, array $labels = []): Gauge;

    /**
     * @throws MetricNotFoundException
     */
    public function getGauge(string $namespace, string $name): Gauge;

    /**
     * @param list<string> $labels
     * @param null|list<float> $buckets
     */
    public function registerHistogram(string $namespace, string $name, string $help, array $labels = [], array|null $buckets = null): Histogram;

    /**
     * @throws MetricNotFoundException
     */
    public function getHistogram(string $namespace, string $name): Histogram;

    /**
     * @param list<string> $labels
     * @param null|list<float<0, 1>> $quantiles
     */
    public function registerSummary(string $namespace, string $name, string $help, array $labels = [], array|null $quantiles = null): Summary;

    /**
     * @throws MetricNotFoundException
     */
    public function getSummary(string $namespace, string $name): Summary;
}
