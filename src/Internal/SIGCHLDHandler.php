<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Revolt\EventLoop;

final class SIGCHLDHandler
{
    private static bool $isInit = false;
    private static array $callbacks = [];

    private function __construct()
    {
    }

    private static function init(): void
    {
        EventLoop::onSignal(SIGCHLD, static function () {
            while (($pid = \pcntl_wait($status, WNOHANG)) > 0) {
                $exitCode = \pcntl_wexitstatus($status) ?: 0;
                foreach (self::$callbacks as $callback) {
                    $callback($pid, $exitCode);
                }
            }
        });
    }

    /**
     * @param \Closure(int, int): void $closure
     */
    public static function onChildProcessExit(\Closure $closure): void
    {
        if (!self::$isInit) {
            self::init();
            self::$isInit = true;
        }

        self::$callbacks[] = $closure;
    }
}
