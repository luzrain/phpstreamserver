<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

/**
 * Handler for redirect standard output to custom stream with colorize filters
 * @internal
 */
final class IOStream
{
    private static bool $isRegistered = false;

    private function __construct()
    {
    }

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public static function register(mixed $stdout = STDOUT, mixed $stderr = STDERR): void
    {
        if (self::$isRegistered) {
            return;
        }

        \stream_filter_register(IOStreamFilter::NAME, IOStreamFilter::class);
        \stream_filter_append($stdout, IOStreamFilter::NAME, STREAM_FILTER_WRITE);
        \stream_filter_append($stderr, IOStreamFilter::NAME, STREAM_FILTER_WRITE);

        self::$isRegistered = true;
        self::restreamOutputBuffer($stdout);
    }

    public static function disableStdout(): void
    {
        IOStreamFilter::$enableOutput = false;
    }

    public static function disableColor(): void
    {
        IOStreamFilter::$enableColors = false;
    }

    /**
     * @param resource $stream
     */
    private static function restreamOutputBuffer(mixed $stream): void
    {
        \ob_start(static function (string $chunk, int $phase) use ($stream): string {
            $isWrite = ($phase & \PHP_OUTPUT_HANDLER_WRITE) === \PHP_OUTPUT_HANDLER_WRITE;
            if ($isWrite && $chunk !== '') {
                \fwrite($stream, $chunk);
                \fflush($stream);
            }

            return '';
        }, 1);
    }
}
