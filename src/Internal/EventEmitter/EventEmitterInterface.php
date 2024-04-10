<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\EventEmitter;

interface EventEmitterInterface
{
    public function on(string $event, \Closure $listener): void;
    public function emit(string $event, mixed ...$arguments): void;
    public function removeListener(string $event, \Closure $listener): void;
}
