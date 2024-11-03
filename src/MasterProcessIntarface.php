<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer;

interface MasterProcessIntarface
{
    public function getMasterContainer(): ContainerInterface;

    public function getWorkerContainer(): ContainerInterface;

    public function &getStatus(): Status;
}
