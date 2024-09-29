<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus;

final class WorkerInfo
{
    public function __construct(
        public string $user,
        public string $name,
        public int $count,
    ) {
    }
}
