<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus;

interface TrafficStatusAwareInterface
{
    public function getTrafficStatus(): TrafficStatus;
}
