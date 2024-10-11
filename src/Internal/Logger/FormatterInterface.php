<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

interface FormatterInterface
{
    public function format(LogEntry $logEntry): string;
}
