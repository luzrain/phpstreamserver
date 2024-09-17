<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Exception;

use Luzrain\PHPStreamServer\Server;

final class NotRunningException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(\sprintf('%s is not running', Server::NAME));
    }
}
