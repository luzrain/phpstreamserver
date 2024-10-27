<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ConnectionStatus;

interface NetworkTrafficCounterAwareInterface
{
    public function getNetworkTrafficCounter(): NetworkTrafficCounter;
}
