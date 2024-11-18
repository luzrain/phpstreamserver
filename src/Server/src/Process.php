<?php

declare(strict_types=1);

namespace PHPStreamServer;

use PHPStreamServer\Internal\Container;
use PHPStreamServer\Plugin\Plugin;

interface Process
{
    public function run(Container $workerContainer): int;

    /**
     * @return list<class-string<Plugin>>
     */
    static public function handleBy(): array;
}
