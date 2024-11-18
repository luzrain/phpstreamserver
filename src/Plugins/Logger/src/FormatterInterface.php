<?php

declare(strict_types=1);

namespace PHPStreamServer\LoggerPlugin;

use PHPStreamServer\LoggerPlugin\Internal\LogEntry;

interface FormatterInterface
{
    public function format(LogEntry $record): string;
}
