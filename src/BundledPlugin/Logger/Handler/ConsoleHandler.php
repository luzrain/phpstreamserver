<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;

use Amp\ByteStream\WritableResourceStream;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Formatter\ConsoleFormatter;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\FormatterInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Handler;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogLevel;
use Luzrain\PHPStreamServer\Internal\Console\Colorizer;
use function Luzrain\PHPStreamServer\Internal\getStderr;
use function Luzrain\PHPStreamServer\Internal\getStdout;

final class ConsoleHandler extends Handler
{
    public const OUTPUT_STDOUT = 1;
    public const OUTPUT_STDERR = 2;

    private WritableResourceStream $stream;
    private bool $colorSupport;

    public function __construct(
        LogLevel $level = LogLevel::DEBUG,
        private readonly FormatterInterface $formatter = new ConsoleFormatter(),
        private int $output = self::OUTPUT_STDERR,
    ) {
        parent::__construct($level);
    }

    public function start(): void
    {
        $this->stream = $this->output === self::OUTPUT_STDERR ? getStderr() : getStdout();
        $this->colorSupport = Colorizer::hasColorSupport($this->stream->getResource());
    }

    public function handle(LogEntry $record): void
    {
        $message = $this->formatter->format($record);
        $message = $this->colorSupport ? Colorizer::colorize($message) : Colorizer::stripTags($message);
        $this->stream->write($message . PHP_EOL);
    }
}
