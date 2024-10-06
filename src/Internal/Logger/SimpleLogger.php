<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final readonly class SimpleLogger implements LoggerInterface
{
    use LoggerTrait;

    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /**
     * @var resource
     */
    private mixed $stream;

    public function __construct()
    {
        $this->stream = STDERR;
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $message = $this->format((string) $level, (string) $message, $context);
        \fwrite($this->stream, $message . PHP_EOL);
    }

    private function format(string $level, string $message, array $context): string
    {
        $context = ContextNormalizer::normalize($context);

        if (\str_contains($message, '{')) {
            $replacements = [];
            foreach ($context as $key => $val) {
                $replacements["{{$key}}"] = \is_array($val) ? '[array]' : (string) $val;
            }
            $message = \strtr($message, $replacements);
        }

        $formattedMessage = \sprintf('%s [%s] %s', \date(\DateTimeInterface::RFC3339), $level, $message);
        $formattedContext = $context !== [] ? ' ' . \json_encode($context, self::DEFAULT_JSON_FLAGS) : '';

        return $formattedMessage . $formattedContext;
    }
}
