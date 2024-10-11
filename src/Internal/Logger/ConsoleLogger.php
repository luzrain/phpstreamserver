<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

use Amp\ByteStream\ResourceStream;
use Amp\ByteStream\WritableStream;
use Luzrain\PHPStreamServer\Internal\Console\Colorizer;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final class ConsoleLogger implements LoggerInterface
{
    use LoggerTrait;

    private readonly WritableStream $stream;
    private readonly FormatterInterface $formatter;
    private bool $colorSupport = false;

    public function __construct(WritableStream $stream)
    {
        $this->stream = $stream;
        $this->formatter = new ConsoleFormatter();

        if ($stream instanceof ResourceStream) {
            $this->colorSupport = Colorizer::hasColorSupport($stream->getResource());
        }
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->doLog(new LogEntry(
            time: new \DateTimeImmutable(),
            level: $level,
            channel: 'app',
            message: (string) $message,
            context: $context,
        ));
    }

    private function doLog(LogEntry $logEntry): void
    {
        $message = $this->formatter->format($logEntry);
        $message = $this->colorSupport ? Colorizer::colorize($message) : Colorizer::stripTags($message);
        $this->stream->write($message . PHP_EOL);
    }
}
