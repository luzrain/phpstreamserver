<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\Formatter\StringFormatter;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\FormatterInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;
use Luzrain\PHPStreamServer\Server;

final class SyslogHandler extends Handler
{
    private FormatterInterface $formatter;

    /**
     * @see https://www.php.net/manual/en/function.openlog.php
     */
    public function __construct(
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
        private string $prefix = Server::SHORTNAME,
        private int $flags = LOG_PID,
        private string|int $facility = LOG_USER,
    ) {
        parent::__construct($level, $channels);
    }

    public function start(): void
    {
        $this->formatter = new StringFormatter(messageFormat: '{channel}.{level} {message} {context}');
        \openlog($this->prefix, $this->flags, $this->facility);
    }

    public function handle(LogEntry $record): void
    {
        \syslog($record->level->toRFC5424(), $this->formatter->format($record));
    }
}
