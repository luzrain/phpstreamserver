<?php

namespace Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal;

use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\GetMetricMessage;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\GetMetricResponse;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\IncreaseCounterMessage;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\ObserveHistorgamMessage;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\ObserveSummaryMessage;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\RegisterMetricMessage;
use Luzrain\PHPStreamServer\BundledPlugin\Metrics\Internal\Message\SetGaugeMessage;
use Luzrain\PHPStreamServer\MessageBus\MessageHandlerInterface;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricNotFoundException as PrometheusMetricNotFoundException;
use Prometheus\Exception\MetricsRegistrationException as PrometheusMetricRegistrationException;
use Prometheus\RegistryInterface as PrometheusRegistryInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use function Amp\weakClosure;

final class MessageBusRegistryHandler
{
    private PrometheusRegistryInterface $registry;

    public function __construct(MessageHandlerInterface $messageHandler)
    {
        $this->registry = new CollectorRegistry(new InMemory(), false);

        $messageHandler->subscribe(RegisterMetricMessage::class, weakClosure($this->registerMetric(...)));
        $messageHandler->subscribe(GetMetricMessage::class, weakClosure($this->getMetric(...)));
        $messageHandler->subscribe(IncreaseCounterMessage::class, weakClosure($this->increaseCounter(...)));
        $messageHandler->subscribe(SetGaugeMessage::class, weakClosure($this->setGauge(...)));
        $messageHandler->subscribe(ObserveHistorgamMessage::class, weakClosure($this->observeHistogram(...)));
        $messageHandler->subscribe(ObserveSummaryMessage::class, weakClosure($this->observeSummary(...)));
    }

    private function registerMetric(RegisterMetricMessage $message): bool
    {
        try {
            match ($message->type) {
                RegisterMetricMessage::TYPE_COUNTER => $this->registry->registerCounter(
                    $message->namespace, $message->name, $message->help, $message->labels,
                ),
                RegisterMetricMessage::TYPE_GAUGE => $this->registry->registerGauge(
                    $message->namespace, $message->name, $message->help, $message->labels,
                ),
                RegisterMetricMessage::TYPE_HISTOGRAM => $this->registry->registerHistogram(
                    $message->namespace, $message->name, $message->help, $message->labels, $message->buckets,
                ),
                RegisterMetricMessage::TYPE_SUMMARY => $this->registry->registerSummary(
                    $message->namespace, $message->name, $message->help, $message->labels, 600, $message->buckets,
                ),
            };
        } catch (PrometheusMetricRegistrationException) {
            return false;
        }

        return true;
    }

    private function getMetric(GetMetricMessage $message): GetMetricResponse|false
    {
        try {
            $counter = match ($message->type) {
                GetMetricMessage::TYPE_COUNTER => $this->registry->getCounter($message->namespace, $message->name),
                GetMetricMessage::TYPE_GAUGE => $this->registry->getGauge($message->namespace, $message->name),
                GetMetricMessage::TYPE_HISTOGRAM => $this->registry->getHistogram($message->namespace, $message->name),
                GetMetricMessage::TYPE_SUMMARY => $this->registry->getSummary($message->namespace, $message->name),
            };
        } catch (PrometheusMetricNotFoundException) {
            return false;
        }

        return new GetMetricResponse($message->type, $message->namespace, $message->name, $counter->getHelp(), $counter->getLabelNames());
    }

    private function increaseCounter(IncreaseCounterMessage $message): void
    {
        $counter = $this->registry->getCounter($message->namespace, $message->name);
        $labels = [...\array_flip($counter->getLabelNames()), ...$message->labels];
        $counter->incBy($message->value, $labels);
    }

    private function setGauge(SetGaugeMessage $message): void
    {
        $gauge = $this->registry->getGauge($message->namespace, $message->name);
        $labels = [...\array_flip($gauge->getLabelNames()), ...$message->labels];
        $message->increase ? $gauge->incBy($message->value, $labels) : $gauge->set($message->value, $labels);
    }

    private function observeHistogram(ObserveHistorgamMessage $message): void
    {
        $histogram = $this->registry->getHistogram($message->namespace, $message->name);
        $labels = [...\array_flip($histogram->getLabelNames()), ...$message->labels];
        foreach ($message->values as $value) {
            $histogram->observe($value, $labels);
        }
    }

    private function observeSummary(ObserveSummaryMessage $message): void
    {
        $summary = $this->registry->getSummary($message->namespace, $message->name);
        $labels = [...\array_flip($summary->getLabelNames()), ...$message->labels];
        foreach ($message->values as $value) {
            $summary->observe($value, $labels);
        }
    }

    public function render(): string
    {
        $renderer = new RenderTextFormat();

        return $renderer->render($this->registry->getMetricFamilySamples());
    }
}
