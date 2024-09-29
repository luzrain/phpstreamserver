<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\SystemPlugin\ServerStatus;

interface NetworkTrafficCounterAwareInterface
{
    public function getNetworkTrafficCounter(): NetworkTrafficCounter;
}
