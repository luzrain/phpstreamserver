<?php

declare(strict_types=1);

namespace PHPStreamServer\LoggerPlugin;

use Amp\Future;
use PHPStreamServer\LoggerPlugin\Internal\LogEntry;

interface HandlerInterface
{
    public function start(): Future;

    public function isHandling(LogEntry $record): bool;

    public function handle(LogEntry $record): void;
}
