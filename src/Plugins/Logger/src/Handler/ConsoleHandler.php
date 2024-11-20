<?php

declare(strict_types=1);

namespace PHPStreamServer\LoggerPlugin\Handler;

use Amp\ByteStream\WritableResourceStream;
use Amp\Future;
use PHPStreamServer\Console\Colorizer;
use PHPStreamServer\LoggerPlugin\Formatter\ConsoleFormatter;
use PHPStreamServer\LoggerPlugin\FormatterInterface;
use PHPStreamServer\LoggerPlugin\Handler;
use PHPStreamServer\LoggerPlugin\Internal\LogEntry;
use PHPStreamServer\LoggerPlugin\Internal\LogLevel;
use function Amp\async;
use function PHPStreamServer\getStderr;
use function PHPStreamServer\getStdout;

final class ConsoleHandler extends Handler
{
    public const OUTPUT_STDOUT = 1;
    public const OUTPUT_STDERR = 2;

    private WritableResourceStream $stream;
    private bool $colorSupport;

    public function __construct(
        private readonly int $output = self::OUTPUT_STDERR,
        LogLevel $level = LogLevel::DEBUG,
        array $channels = [],
        private readonly FormatterInterface $formatter = new ConsoleFormatter(),
    ) {
        parent::__construct($level, $channels);
    }

    public function start(): Future
    {
        $this->stream = $this->output === self::OUTPUT_STDERR ? getStderr() : getStdout();
        $this->colorSupport = Colorizer::hasColorSupport($this->stream->getResource());

        return async(static fn() => null);
    }

    public function handle(LogEntry $record): void
    {
        $message = $this->formatter->format($record);
        $message = $this->colorSupport ? Colorizer::colorize($message) : Colorizer::stripTags($message);
        $this->stream->write($message . "\n");
    }
}
