<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Luzrain\PHPStreamServer\Exception\UserChangeException;
use Revolt\EventLoop\DriverFactory;

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
        static $file;
        if (!isset($file)) {
            $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $file = \end($backtrace)['file'];
        }
        return $file;
    }

    public static function getDriverName(): string
    {
        return (new \ReflectionObject((new DriverFactory())->create()))->getShortName();
    }

    public static function getCurrentUser(): string
    {
        return (\posix_getpwuid(\posix_geteuid()) ?: [])['name'] ?? (string) \posix_geteuid();
    }

    public static function getCurrentGroup(): string
    {
        return (\posix_getgrgid(\posix_getegid()) ?: [])['name'] ?? (string) \posix_getegid();
    }

    public static function humanFileSize(int $bytes): string
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
     * @throws UserChangeException
     */
    public static function setUserAndGroup(string|null $user = null, string|null $group = null): void
    {
        if ($user === null && $group === null) {
            return;
        }

        if (\posix_getuid() !== 0) {
            throw new UserChangeException('You must have the root privileges to change the user and group');
        }

        $user ??= self::getCurrentUser();

        // Get uid
        if ($userInfo = \posix_getpwnam($user)) {
            $uid = $userInfo['uid'];
        } else {
            throw new UserChangeException(\sprintf('User "%s" does not exist', $user));
        }

        // Get gid
        if ($group === null) {
            $gid = $userInfo['gid'];
        } elseif ($groupInfo = \posix_getgrnam($group)) {
            $gid = $groupInfo['gid'];
        } else {
            throw new UserChangeException(\sprintf('Group "%s" does not exist', $group));
        }

        // Set uid and gid
        if ($uid !== \posix_getuid() || $gid !== \posix_getgid()) {
            if (!\posix_setgid($gid) || !\posix_initgroups($userInfo['name'], $gid) || !\posix_setuid($uid)) {
                throw new UserChangeException('Changing guid or uid fails');
            }
        }
    }

    public static function reportErrors(): bool
    {
        return (\error_reporting() & \E_ERROR) === \E_ERROR;
    }

    public static function isRunning(string $pidFile): bool
    {
        return (0 !== $pid = self::getPid($pidFile)) && \posix_kill($pid, 0);
    }

    public static function getPid(string $pidFile): int
    {
        return \is_file($pidFile) ? (int) \file_get_contents($pidFile) : 0;
    }

    public static function getRunDirectory(): string
    {
        static $dir;
        return $dir ??= \posix_access('/run/', POSIX_R_OK | POSIX_W_OK) ? '/run' : \sys_get_temp_dir();
    }

    public static function getDefaultPidFile(): string
    {
        return \sprintf('%s/phpss%s.pid', self::getRunDirectory(), \hash('xxh32', self::getStartFile()));
    }

    public static function getDefaultSocketFile(): string
    {
        return \sprintf('%s/phpss%s.socket', self::getRunDirectory(), \hash('xxh32', self::getStartFile()));
    }

    public static function absoluteBinaryPath(string $binary): string
    {
        if (!\str_starts_with($binary, '/') && \is_string($absoluteBinaryPath = \shell_exec("command -v $binary"))) {
            $binary = \trim($absoluteBinaryPath);
        }

        return $binary;
    }

    public static function memoryUsageByPid(int $pid): int
    {
        $out = \shell_exec("ps -o rss= -p $pid 2>/dev/null");

        return ((int) \trim((string) $out)) * 1024;
    }
}
