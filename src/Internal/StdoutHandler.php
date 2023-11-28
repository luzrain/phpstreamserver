<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

use Luzrain\PhpRunner\Console\Colorizer;

/**
 * Handler for redirect standard output to custom stream
 */
final class StdoutHandler
{
    /**
     * @var resource
     */
    private static $stream;
    private static bool $hasColorSupport;

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

        self::$hasColorSupport = Colorizer::hasColorSupport(self::$stream);
        self::restreamOutputBuffer();
    }

    private static function restreamOutputBuffer(): void
    {
        \ob_start(static function (string $chunk, int $phase): string {
            $isWrite = ($phase & \PHP_OUTPUT_HANDLER_WRITE) === \PHP_OUTPUT_HANDLER_WRITE;

            if ($isWrite && $chunk !== '') {
                $txt = self::$hasColorSupport ? Colorizer::colorize($chunk) : \strip_tags($chunk);
                fwrite(self::$stream, $txt);
                fflush(self::$stream);
            }

            return '';
        }, 1);
    }
}
