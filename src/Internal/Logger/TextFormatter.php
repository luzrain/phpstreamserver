<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

final class TextFormatter implements FormatterInterface
{
    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function __construct()
    {
    }

    public function format(LogEntry $logEntry): string
    {
        $context = ContextNormalizer::normalize($logEntry->context);
        $time = $logEntry->time->format(\DateTimeImmutable::ATOM);
        $level = \strtoupper($logEntry->level);
        $channel = \strtolower($logEntry->channel);
        $message = ContextNormalizer::contextreplacement($logEntry->message, $context);
        $context = \json_encode($context, self::DEFAULT_JSON_FLAGS);

        return \rtrim(\sprintf("[%s] %s.%s: %s %s\n", $time, $level, $channel, $message, $context));
    }
}
