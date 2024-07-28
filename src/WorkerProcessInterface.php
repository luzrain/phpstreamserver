<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

use Luzrain\PHPStreamServer\Plugin\Plugin;
use Luzrain\PHPStreamServer\ReloadStrategy\ReloadStrategy;

interface WorkerProcessInterface extends ProcessInterface
{
    public function stop(int $code = 0): void;

    public function reload(): void;

    public function addReloadStrategies(ReloadStrategy ...$reloadStrategies): void;

    public function startPlugin(Plugin $plugin): void;
}
