<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Console;

/**
 * Handler for redirect standard output to custom stream
 */
final class StdoutHandler
{
    private static bool $isRegistered = false;
    private static bool $enableColors = true;

    private function __construct()
    {
    }

    /**
     * @param resource|string $stream
     */
    public static function register(mixed $stream = 'php://stdout'): void
    {
        if (self::$isRegistered) {
            return;
        }

        if (\is_string($stream)) {
            $stream = \fopen($stream, 'ab');
        }

        if (!(\is_resource($stream) && \get_resource_type($stream) === 'stream')) {
            throw new \InvalidArgumentException(\sprintf('$stream must be of type string or resource (stream), %s given', \get_debug_type($stream)));
        }

        self::$isRegistered = true;
        self::restreamOutputBuffer($stream);
    }

    public static function disableStdout(): void
    {
        self::$isRegistered = false;
        \ob_end_clean();
        \ob_start(static fn() => '', 1);
    }

    public static function disableColor(): void
    {
        self::$enableColors = false;
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
                $text = self::$enableColors && $hasColorSupport ? Colorizer::colorize($chunk) : Colorizer::stripTags($chunk);
                \fwrite($stream, $text);
                \fflush($stream);
            }

            return '';
        }, 1);
    }
}
