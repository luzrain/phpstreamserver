<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

use Luzrain\PhpRunner\Exception\HttpException;

/**
 * Reload worker each time after exception occurs
 */
final class ExceptionReloadStrategy implements ReloadStrategyInterface
{
    /** @var array<class-string<\Throwable>> */
    private array $allowedExceptions = [
        HttpException::class,
    ];

    /**
     * @param array<class-string<\Throwable>> $allowedExceptions exceptions that will not be trigger reloading
     */
    public function __construct(array $allowedExceptions = [])
    {
        \array_push($this->allowedExceptions, ...$allowedExceptions);
    }

    public function onTimer(): bool
    {
        return false;
    }

    public function onRequest(): bool
    {
        return false;
    }

    public function onException(): bool
    {
        return true;
    }

    public function shouldReload(mixed $event = null): bool
    {
        foreach ($this->allowedExceptions as $allowedExceptionClass) {
            if ($event instanceof $allowedExceptionClass) {
                return false;
            }
        }

        return true;
    }
}
