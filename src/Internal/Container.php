<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

interface Container
{
    public function set(string $id, mixed $value): void;
    public function get(string $id): mixed;
    public function has(string $id): bool;
}
