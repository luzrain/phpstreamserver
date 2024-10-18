<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Logger\Handler;

use Amp\ByteStream\WritableResourceStream;
use Luzrain\PHPStreamServer\Internal\Console\Colorizer;
use Luzrain\PHPStreamServer\Plugin\Logger\Formatter\ConsoleFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use function Amp\ByteStream\getStderr;

final class StdErrHandler extends AbstractProcessingHandler
{
    private WritableResourceStream $stream;
    private bool $colorSupport;

    public function __construct(Level $level = Level::Debug, bool $bubble = true)
    {
        $this->stream = getStderr();
        $this->colorSupport = Colorizer::hasColorSupport($this->stream->getResource());

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $colorizedMessage = $record->formatted;
        $colorizedMessage = $this->colorSupport ? Colorizer::colorize($colorizedMessage) : Colorizer::stripTags($colorizedMessage);
        $this->stream->write($colorizedMessage . PHP_EOL);
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new ConsoleFormatter();
    }
}
