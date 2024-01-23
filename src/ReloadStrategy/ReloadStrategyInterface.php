<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

interface ReloadStrategyInterface
{
    /**
     * @var int timer interval in seconds
     */
    final public const TIMER_INTERVAL = 30;

    /**
     * @var int process exit code
     */
    public const EXIT_CODE = 100;

    /**
     * Should the shouldReload() method be called every N seconds?
     */
    public function onTimer(): bool;

    /**
     * Should the shouldReload() method be called after every request?
     */
    public function onRequest(): bool;

    /**
     * Should the shouldReload() method be called after an exception is thrown?
     */
    public function onException(): bool;

    /**
     * If the method returns true, the worker is immediately reloaded.
     * @param mixed $event Could be a request object or an exception object, depending on the context.
     */
    public function shouldReload(mixed $event = null): bool;
}
