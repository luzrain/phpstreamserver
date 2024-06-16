<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\ReloadStrategy;

use Amp\Http\Server\ClientException;
use Amp\Http\Server\HttpErrorException;

/**
 * Reload worker each time after exception occurs
 */
class ExceptionReloadStrategy implements ReloadStrategyInterface
{
    /** @var array<class-string<\Throwable>> */
    private array $allowedExceptions = [
        ClientException::class,
        HttpErrorException::class,
    ];

    /**
     * @param array<class-string<\Throwable>> $allowedExceptions exceptions that will not be trigger reloading
     */
    public function __construct(array $allowedExceptions = [])
    {
        \array_push($this->allowedExceptions, ...$allowedExceptions);
    }

    public function shouldReload(int $eventCode, mixed $eventObject = null): bool
    {
        if ($eventCode !== self::EVENT_CODE_EXCEPTION) {
            return false;
        }

        foreach ($this->allowedExceptions as $allowedExceptionClass) {
            if ($eventObject instanceof $allowedExceptionClass) {
                return false;
            }
        }

        return true;
    }
}
