<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

final class ConsoleFormatter implements FormatterInterface
{
    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private const LEVELS_COLOR_MAP = [
        'debug' => '<color;fg=15>DEBUG</>',
        'info' => '<color;fg=116>INFO</>',
        'notice' => '<color;fg=38>NOTICE</>',
        'warning' => '<color;fg=yellow>WARNING</>',
        'error' => '<color;fg=red>ERROR</>',
        'critical' => '<color;fg=red>CRITICAL</>',
        'alert' => '<color;fg=red>ALERT</>',
        'emergency' => '<color;bg=red>EMERGENCY</>',
    ];

    public function __construct()
    {
    }

    public function format(LogEntry $logEntry): string
    {
        $context = ContextNormalizer::normalize($logEntry->context);
        $time = $logEntry->time->format('Y-m-d H:i:s');
        $level = self::LEVELS_COLOR_MAP[$logEntry->level] ?? $logEntry->level;
        $channel = $logEntry->channel;
        $message = ContextNormalizer::contextreplacement($logEntry->message, $context);
        $context = $context !== [] ? '' . \json_encode($context, self::DEFAULT_JSON_FLAGS) : '';

        return \rtrim(\sprintf("%s  %s\t<color;fg=green>%s</>\t%s %s", $time, $level, $channel, $message, $context));
    }
}
