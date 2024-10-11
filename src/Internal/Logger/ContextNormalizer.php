<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

/**
 * @internal
 */
final class ContextNormalizer
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    public static function normalize(array $context): array
    {
        foreach ($context as $key => $val) {
            $context[$key] = match(true) {
                \is_array($val) => self::normalize($val),
                $val instanceof \Throwable => self::formatException($val),
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

    /**
     * @param array<string, string> $context
     */
    public static function contextreplacement(string $message, array $context): string
    {
        if (\str_contains($message, '{')) {
            $replacements = [];
            foreach ($context as $key => $val) {
                $replacements["{{$key}}"] = \is_array($val) ? '[array]' : (string) $val;
            }
            $message = \strtr($message, $replacements);
        }

        return $message;
    }

    private static function formatException(\Throwable $e): string
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
