<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\ReloadStrategy;

interface ReloadStrategyInterface
{
    /**
     * @var int Periodic timer tick
     */
    final public const EVENT_CODE_TIMER = 1;

    /**
     * @var int Request recieved
     */
    final public const EVENT_CODE_REQUEST = 2;

    /**
     * @var int Exception occurs
     */
    final public const EVENT_CODE_EXCEPTION = 3;

    /**
     * If the method returns true, the worker should be reloaded immediately.
     *
     * @param int $eventCode one of the event codes from the EVENT_CODE_ constants above.
     * @param mixed $eventObject could be a request object, exception object, or null, depending on the eventCode.
     */
    public function shouldReload(int $eventCode, mixed $eventObject = null): bool;
}
