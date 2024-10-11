<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

/**
 * Handler for redirect standard output to custom stream with colorize filters
 * @internal
 */
final class StdoutHandler
{
    private static bool $isRegistered = false;

    private function __construct()
    {
    }

    /**
     * @param resource|string $stream
     */
    public static function register(mixed $stream = 'php://stdout'): void
    {
        if (self::$isRegistered) {
            throw new \RuntimeException('StdoutHandler already registered');
        }

        if (\is_string($stream)) {
            $stream = \fopen($stream, 'ab');
        }

        self::$isRegistered = true;
        self::restreamOutputBuffer($stream);
    }

    public static function disableStdout(): void
    {
        \ob_end_clean();
        \ob_start(static fn() => '', 1);
    }

    /**
     * @param resource $stream
     */
    private static function restreamOutputBuffer(mixed $stream): void
    {
        $hasColorSupport = Colorizer::hasColorSupport($stream);
        \ob_start(static function (string $chunk, int $phase) use ($hasColorSupport, $stream): string {
            $isWrite = ($phase & \PHP_OUTPUT_HANDLER_WRITE) === \PHP_OUTPUT_HANDLER_WRITE;
            if ($isWrite && $chunk !== '') {
                $buffer = $hasColorSupport ? Colorizer::colorize($chunk) : Colorizer::stripTags($chunk);
                \fwrite($stream, $buffer);
                \fflush($stream);
            }

            return '';
        }, 1);
    }
}
