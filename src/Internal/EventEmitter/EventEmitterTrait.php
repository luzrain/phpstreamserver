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
    private array $listeners = [];

    public function on(string $event, \Closure $listener): void
    {
        $this->listeners[$event] ??= [];
        $this->listeners[$event][\spl_object_id($listener)] = $listener;
    }

    public function emit(string $event, mixed ...$arguments): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener(...$arguments);
        }
    }

    public function removeListener(string $event, \Closure $listener): void
    {
        if (isset($this->listeners[$event])) {
            unset($this->listeners[$event][\spl_object_id($listener)]);
            if ($this->listeners[$event] === []) {
                unset($this->listeners[$event]);
            }
        }
    }
}
