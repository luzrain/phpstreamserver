<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Exception;

use Luzrain\PHPStreamServer\Server;

final class AlreadyRunningException extends \Exception
{
    public function __construct()
    {
        parent::__construct(\sprintf('%s already running', Server::NAME));
    }
}
