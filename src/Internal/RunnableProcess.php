<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal;

interface RunnableProcess
{
    public function run(WorkerContext $workerContext): int;
}
