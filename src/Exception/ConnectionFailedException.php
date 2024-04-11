<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Exception;

final class ConnectionFailedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('connection failed');
    }
}
