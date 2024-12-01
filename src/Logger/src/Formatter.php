<?php

declare(strict_types=1);

namespace PHPStreamServer\Plugin\Logger;

use PHPStreamServer\Plugin\Logger\Internal\LogEntry;

interface Formatter
{
    public function format(LogEntry $record): string;
}
