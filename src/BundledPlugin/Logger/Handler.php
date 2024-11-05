<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;

abstract class Handler implements HandlerInterface
{
    protected readonly LogLevel $level;

    public function __construct(LogLevel $level = LogLevel::DEBUG)
    {
        $this->level = $level;
    }

    public function isHandling(LogEntry $record): bool
    {
        return $record->level->value >= $this->level->value;
    }
}
