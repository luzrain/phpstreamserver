<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

/**
 * @internal
 */
final class Functions
{
    private function __construct()
    {
    }

    public static function getStartFile(): string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        return \end($backtrace)['file'];
    }

    public static function getCurrentUser(): string
    {
        return (\posix_getpwuid(\posix_getuid()) ?: [])['name'] ?? 'unknown';
    }

    /**
     * @param resource $resource
     */
    public static function streamRead($resource, bool $blocking = false): string
    {
        $isBlocked = \stream_get_meta_data($resource)['blocked'];
        $buffer = '';
        \stream_set_blocking($resource, $blocking);
        while (($s = \fread($resource, 1024) ?: '') !== '') {
            $buffer .= $s;
        }
        \stream_set_blocking($resource, $isBlocked);
        return $buffer;
    }

    /**
     * @param resource $resource
     */
    public static function streamWrite($resource, string $data): void
    {
        \fwrite($resource, $data);
        \fflush($resource);
    }
}
