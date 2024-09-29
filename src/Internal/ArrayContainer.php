<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

/**
 * @internal
 */
final class ArrayContainer implements Container
{
    private array $container = [];

    public function set(string $id, mixed $value): void
    {
        if ($value === null) {
            unset($this->container[$id]);
        } else {
            $this->container[$id] = $value;
        }
    }

    public function get(string $id): mixed
    {
        return $this->container[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->container[$id]);
    }
}
