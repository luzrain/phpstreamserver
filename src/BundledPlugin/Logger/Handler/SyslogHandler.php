<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;

use Amp\Future;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Formatter\StringFormatter;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\FormatterInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;
use Luzrain\PHPStreamServer\Server;
use function Amp\async;

final class SyslogHandler extends Handler
{
    private FormatterInterface $formatter;

    /**
     * @see https://www.php.net/manual/en/function.openlog.php
     */
    public function __construct(
        private readonly string $prefix = Server::SHORTNAME,
        private readonly int $flags = 0,
        private readonly string|int $facility = LOG_USER,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
    ) {
        parent::__construct($level, $channels);
    }

    public function start(): Future
    {
        $this->formatter = new StringFormatter(messageFormat: '{channel}.{level} {message} {context}');
        \openlog($this->prefix, $this->flags, $this->facility);

        return async(static fn () => null);
    }

    public function handle(LogEntry $record): void
    {
        \syslog($record->level->toRFC5424(), $this->formatter->format($record));
    }
}
