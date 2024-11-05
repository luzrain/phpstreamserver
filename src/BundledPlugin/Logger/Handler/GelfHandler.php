<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;

final class GelfHandler extends Handler
{
    public function __construct(LogLevel $level = LogLevel::DEBUG)
    {
        parent::__construct($level);
    }

    public function start(): void
    {
    }

    public function handle(LogEntry $record): void
    {
    }
}
