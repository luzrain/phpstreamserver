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

        return end($backtrace)['file'];
    }

    public static function getCurrentUser(): string
    {
        return (\posix_getpwuid(\posix_getuid()) ?: [])['name'] ?? 'unknown';
    }
}
