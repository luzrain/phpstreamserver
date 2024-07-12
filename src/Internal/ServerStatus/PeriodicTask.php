<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

final class PeriodicTask
{
    public function __construct(
        public string $user,
        public string $name,
    ) {
    }
}
