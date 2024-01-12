<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Internal;

use Luzrain\PhpRunner\Exception\UserChangeException;

/**
 * @internal
 */
final class Functions
{
    private function __construct()
    {
    }

    /**
     * @psalm-suppress PossiblyUndefinedArrayOffset
     */
    public static function getStartFile(): string
    {
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

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
        \stream_set_blocking($resource, $blocking);
        $buffer = (string) \stream_get_contents($resource, -1);
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
        $currentUser = self::getCurrentUser();
        $user ??= $currentUser;

        if (\posix_getuid() !== 0 && $user !== $currentUser) {
            throw new UserChangeException('You must have the root privileges to change the user and group');
        }

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
}
