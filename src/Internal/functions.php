<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Revolt\EventLoop\DriverFactory;

function getStartFile(): string
{
    static $file;
    if (!isset($file)) {
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $file = \end($backtrace)['file'];
    }
    return $file;
}

function getDriverName(): string
{
    return (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
}

function getCurrentUser(): string
{
    return (\posix_getpwuid(\posix_geteuid()) ?: [])['name'] ?? (string) \posix_geteuid();
}

function getCurrentGroup(): string
{
    return (\posix_getgrgid(\posix_getegid()) ?: [])['name'] ?? (string) \posix_getegid();
}

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

function reportErrors(): bool
{
    return (\error_reporting() & \E_ERROR) === \E_ERROR;
}

function isRunning(string $pidFile): bool
{
    return (0 !== $pid = getPid($pidFile)) && \posix_kill($pid, 0);
}

function getPid(string $pidFile): int
{
    return \is_file($pidFile) ? (int) \file_get_contents($pidFile) : 0;
}

function getRunDirectory(): string
{
    static $dir;
    return $dir ??= \posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir();
}

function getDefaultPidFile(): string
{
    return \sprintf('%s/phpss%s.pid', getRunDirectory(), \hash('xxh32', getStartFile()));
}

function getDefaultSocketFile(): string
{
    return \sprintf('%s/phpss%s.socket', getRunDirectory(), \hash('xxh32', getStartFile()));
}

function absoluteBinaryPath(string $binary): string
{
    if (!\str_starts_with($binary, '/') && \is_string($absoluteBinaryPath = \shell_exec("command -v $binary"))) {
        $binary = \trim($absoluteBinaryPath);
    }

    return $binary;
}

function memoryUsageByPid(int $pid): int
{
    $out = \shell_exec("ps -o rss= -p $pid 2>/dev/null");

    return ((int) \trim((string) $out)) * 1024;
}
