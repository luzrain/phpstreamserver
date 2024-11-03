<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Revolt\EventLoop\DriverFactory;

/**
 * @internal
 */
function getStartFile(): string
{
    static $file;
    if (!isset($file)) {
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = \end($backtrace)['file'];
    }
    return $file;
}

/**
 * @internal
 */
function getDriverName(): string
{
    return (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
}

/**
 * @internal
 */
function getCurrentUser(): string
{
    return (\posix_getpwuid(\posix_geteuid()) ?: [])['name'] ?? (string) \posix_geteuid();
}

/**
 * @internal
 */
function getCurrentGroup(): string
{
    return (\posix_getgrgid(\posix_getegid()) ?: [])['name'] ?? (string) \posix_getegid();
}

/**
 * @internal
 */
function humanFileSize(int $bytes): string
{
    if ($bytes < 1024) {
        return "$bytes B";
    }
    $bytes = \round($bytes / 1024, 0);
    if ($bytes < 1024) {
        return "$bytes KiB";
    }
    $bytes = \round($bytes / 1024, 1);
    if ($bytes < 1024) {
        return "$bytes MiB";
    }
    $bytes = \round($bytes / 1024, 1);
    if ($bytes < 1024) {
        return "$bytes GiB";
    }
    $bytes = \round($bytes / 1024, 1);
    return "$bytes PiB";
}

/**
 * @internal
 */
function reportErrors(): bool
{
    return (\error_reporting() & \E_ERROR) === \E_ERROR;
}

/**
 * @internal
 */
function isRunning(string $pidFile): bool
{
    return (0 !== $pid = getPid($pidFile)) && \posix_kill($pid, 0);
}

/**
 * @internal
 */
function getPid(string $pidFile): int
{
    return \is_file($pidFile) ? (int) \file_get_contents($pidFile) : 0;
}

/**
 * @internal
 */
function getRunDirectory(): string
{
    static $dir;
    return $dir ??= \posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir();
}

/**
 * @internal
 */
function getDefaultPidFile(): string
{
    return \sprintf('%s/phpss%s.pid', getRunDirectory(), \hash('xxh32', getStartFile()));
}

/**
 * @internal
 */
function getDefaultSocketFile(): string
{
    return \sprintf('%s/phpss%s.socket', getRunDirectory(), \hash('xxh32', getStartFile()));
}

/**
 * @internal
 */
function absoluteBinaryPath(string $binary): string
{
    if (!\str_starts_with($binary, '/') && \is_string($absoluteBinaryPath = \shell_exec("command -v $binary"))) {
        $binary = \trim($absoluteBinaryPath);
    }

    return $binary;
}

/**
 * @internal
 */
function memoryUsageByPid(int $pid): int
{
    if (PHP_VERSION_ID >= 80300 && \is_file("/proc/$pid/statm")) {
        $pagesize = \posix_sysconf(POSIX_SC_PAGESIZE);
        $statm = \trim(\file_get_contents("/proc/$pid/statm"));
        $statm = \explode(' ', $statm);
        $vmrss = ($statm[1] ?? 0) * $pagesize;
    } else {
        $out = \shell_exec("ps -o rss= -p $pid 2>/dev/null");
        $vmrss = ((int) \trim((string) $out)) * 1024;
    }

    return $vmrss;
}
