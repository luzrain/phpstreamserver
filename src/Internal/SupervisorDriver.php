<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

use Revolt\EventLoop\CallbackType;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Driver\StreamSelectDriver;
use Revolt\EventLoop\Suspension;

final class SupervisorDriver implements Driver
{
    private Driver $innerDriver;
    private $callbacks = [];

    public function __construct()
    {
        // Force use StreamSelectDriver in the master process because it uses pcntl_signal to handle signals, and it works better for this case.
        $this->innerDriver = new StreamSelectDriver();

        $this->onSignal(SIGCHLD, function () {
            while (($pid = \pcntl_wait($status, WNOHANG)) > 0) {
                $exitCode = \pcntl_wexitstatus($status) ?: 0;
                foreach ($this->callbacks as $callback) {
                    $callback($pid, $exitCode);
                }
            }
        });
    }

    public function onChildProcessExit(\Closure $closure): void
    {
        $this->callbacks[] = $closure;
    }

    public function run(): void
    {
        $this->innerDriver->run();
    }

    public function stop(): void
    {
        $this->innerDriver->stop();
    }

    public function getSuspension(): Suspension
    {
        return $this->innerDriver->getSuspension();
    }

    public function isRunning(): bool
    {
        return $this->innerDriver->isRunning();
    }

    public function queue(\Closure $closure, ...$args): void
    {
        $this->innerDriver->queue($closure, ...$args);
    }

    public function defer(\Closure $closure): string
    {
        return $this->innerDriver->defer($closure);
    }

    public function delay(float $delay, \Closure $closure): string
    {
        return $this->innerDriver->delay($delay, $closure);
    }

    public function repeat(float $interval, \Closure $closure): string
    {
        return $this->innerDriver->repeat($interval, $closure);
    }

    public function onReadable(mixed $stream, \Closure $closure): string
    {
        return $this->innerDriver->onReadable($stream, $closure);
    }

    public function onWritable(mixed $stream, \Closure $closure): string
    {
        return $this->innerDriver->onWritable($stream, $closure);
    }

    public function onSignal(int $signal, \Closure $closure): string
    {
        return $this->innerDriver->onSignal($signal, $closure);
    }

    public function enable(string $callbackId): string
    {
        return $this->innerDriver->enable($callbackId);
    }

    public function cancel(string $callbackId): void
    {
        $this->innerDriver->cancel($callbackId);
    }

    public function disable(string $callbackId): string
    {
        return $this->innerDriver->disable($callbackId);
    }

    public function reference(string $callbackId): string
    {
        return $this->innerDriver->reference($callbackId);
    }

    public function unreference(string $callbackId): string
    {
        return $this->innerDriver->unreference($callbackId);
    }

    public function setErrorHandler(?\Closure $errorHandler): void
    {
        $this->innerDriver->setErrorHandler($errorHandler);
    }

    public function getErrorHandler(): ?\Closure
    {
        return $this->innerDriver->getErrorHandler();
    }

    public function getHandle(): mixed
    {
        return $this->innerDriver->getHandle();
    }

    public function getIdentifiers(): array
    {
        return $this->innerDriver->getIdentifiers();
    }

    public function getType(string $callbackId): CallbackType
    {
        return $this->innerDriver->getType($callbackId);
    }

    public function isEnabled(string $callbackId): bool
    {
        return $this->innerDriver->isEnabled($callbackId);
    }

    public function isReferenced(string $callbackId): bool
    {
        return $this->innerDriver->isReferenced($callbackId);
    }

    public function __debugInfo(): array
    {
        return $this->innerDriver->__debugInfo();
    }
}
