<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public function __construct(private readonly string|null $logFile = null)
    {
        if ($this->logFile !== null && !\is_file($this->logFile)) {
            if (!\is_dir(\dirname($this->logFile))) {
                \mkdir(\dirname($this->logFile), 0777, true);
            }
            \touch($this->logFile);
            \chmod($this->logFile, 0644);
        }
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $message = $this->format((string) $level, (string) $message, $context);

        echo $message . "\n";

        if ($this->logFile !== null) {
            \file_put_contents($this->logFile, $message . "\n", FILE_APPEND);
        }
    }

    private function format(string $level, string $message, array $context): string
    {
        $context = $this->normalizeContext($context);

        if (\str_contains($message, '{')) {
            $replacements = [];
            foreach ($context as $key => $val) {
                $replacements["{{$key}}"] = \is_array($val) ? '[array]' : (string) $val;
            }
            $message = \strtr($message, $replacements);
        }

        $formattedMessage = \sprintf('%s [%s] %s', \date(\DateTimeInterface::RFC3339), $level, $message);
        $formattedContext = !empty($context) ? ' ' . \json_encode($context, self::DEFAULT_JSON_FLAGS) : '';

        return $formattedMessage . $formattedContext;
    }

    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $val) {
            $context[$key] = match(true) {
                \is_array($val) => $this->normalizeContext($val),
                $val instanceof \Throwable => $this->formatException($val),
                $val instanceof \DateTimeInterface => $val->format(\DateTimeInterface::RFC3339),
                $val instanceof \JsonSerializable => \json_decode($val->jsonSerialize()),
                $val instanceof \Stringable => (string) $val,
                \is_scalar($val) || \is_null($val) => $val,
                \is_object($val) => '[object ' . $val::class . ']',
                default => '[' . \get_debug_type($val) . ']',
            };
        }

        return $context;
    }

    private function formatException(\Throwable $e): string
    {
        return \sprintf(
            "[object] (%s(code:%d): %s at %s:%d)\n[stacktrace]\n%s",
            $e::class,
            $e->getCode(),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );
    }
}
