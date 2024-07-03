<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin;

use Luzrain\PHPStreamServer\Internal\ReloadStrategyTrigger;
use Luzrain\PHPStreamServer\Internal\ServerStatus\TrafficStatus;
use Psr\Log\LoggerInterface;

interface Plugin
{
    public function start(LoggerInterface $logger, TrafficStatus $trafficStatus, ReloadStrategyTrigger $reloadStrategyTrigger): void;
}
