<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\EventEmitter;

/**
 * @psalm-require-implements EventEmitterInterface
 */
trait EventEmitterTrait
{
    /**
     * @var array<string, array<int, \Closure>>
     */
    private array $eventListeners = [];

    public function on(string $event, \Closure $listener): void
    {
        $this->eventListeners[$event] ??= [];
        $this->eventListeners[$event][\spl_object_id($listener)] = $listener;
    }

    private function emit(string $event, mixed ...$arguments): void
    {
        foreach ($this->eventListeners[$event] ?? [] as $listener) {
            $listener(...$arguments);
        }
    }

    public function removeListener(string $event, \Closure $listener): void
    {
        if (isset($this->eventListeners[$event])) {
            unset($this->eventListeners[$event][\spl_object_id($listener)]);
            if ($this->eventListeners[$event] === []) {
                unset($this->eventListeners[$event]);
            }
        }
    }
}
