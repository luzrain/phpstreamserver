<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus;

final class PeriodicWorkerInfo
{
    public function __construct(
        public string $user,
        public string $name,
    ) {
    }
}
