<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;

final class GelfHandler extends Handler
{
    public function __construct(
        private readonly string $address,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
    ) {
        parent::__construct($level, $channels);
    }

    public function start(): void
    {
    }

    public function handle(LogEntry $record): void
    {
    }
}
