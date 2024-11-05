<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;

interface HandlerInterface
{
    public function start(): void;

    public function isHandling(LogEntry $record): bool;

    public function handle(LogEntry $record): void;
}
