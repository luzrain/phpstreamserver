<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Exception;

final class ConnectionClosedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('connection closed');
    }
}
