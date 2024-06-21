<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\ServerStatus\Message;

use Luzrain\PHPStreamServer\Internal\ServerStatus\Connection;

final readonly class Connections
{
    /**
     * @param list<Connection> $connections
     */
    public function __construct(public array $connections)
    {
    }
}
