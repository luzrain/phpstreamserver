<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

/**
 * Handler for redirect standard output to custom stream
 */
final class StdoutHandler
{
    /**
     * @var resource
     */
    private static $stream;

    private function __construct()
    {
    }

    /**
     * @param resource|string $stream
     */
    public static function register(mixed $stream): void
    {
        if (\is_resource($stream) && \get_resource_type($stream) === 'stream') {
            self::$stream = $stream;
        } elseif (\is_string($stream)) {
            self::$stream = \fopen($stream, 'ab');
        } else {
            throw new \InvalidArgumentException(\sprintf('$stream must be of type string or resource (stream), %s given', \get_debug_type($stream)));
        }

        self::restreamOutputBuffer();
    }

    private static function restreamOutputBuffer(): void
    {
        \ob_start(static function (string $chunk, int $phase): string {
            $isWrite = ($phase & \PHP_OUTPUT_HANDLER_WRITE) === \PHP_OUTPUT_HANDLER_WRITE;

            if ($isWrite && $chunk !== '') {
                fwrite(self::$stream, $chunk);
                fflush(self::$stream);
            }

            return '';
        }, 1);
    }
}
