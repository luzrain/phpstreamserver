<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

final class JsonFormatter implements FormatterInterface
{
    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function __construct()
    {
    }

    public function format(LogEntry $logEntry): string
    {
        $context = ContextNormalizer::normalize($logEntry->context);
        $time = $logEntry->time->format(\DateTimeImmutable::ATOM);
        $level = \strtolower($logEntry->level);
        $channel = \strtolower($logEntry->channel);
        $message = ContextNormalizer::contextreplacement($logEntry->message, $context);

        $log = (object) [
            'message' => $message,
            'context' => (object) $context,
            'level' => $level,
            'channel' => $channel,
            'time' => $time,
        ];

        return \json_encode($log, self::DEFAULT_JSON_FLAGS);
    }
}
