<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Logger;

use Amp\ByteStream\WritableStream;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * @internal
 */
final readonly class SimpleLogger implements LoggerInterface
{
    use LoggerTrait;

    private const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private const LEVELS_COLOR_MAP = [
        'debug' => '<color;fg=15>DEBUG</>',
        'info' => '<color;fg=116>INFO</>',
        'notice' => '<color;fg=38>NOTICE</>',
        'warning' => '<color;fg=yellow>WARNING</>',
        'error' => '<color;fg=red>ERROR</>',
        'critical' => '<color;fg=red>CRITICAL</>',
        'alert' => '<color;fg=red>ALERT</>',
        'emergency' => '<color;bg=red>EMERGENCY</>',
    ];

    public function __construct(private WritableStream $stream)
    {
    }

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $formattedMessage = $this->format((string) $level, (string) $message, $context);
        $this->stream->write($formattedMessage. PHP_EOL);
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

        $date = \date('Y-m-d H:i:s');
        $level = self::LEVELS_COLOR_MAP[$level] ?? $level;
        $context = $context !== [] ? '' . \json_encode($context, self::DEFAULT_JSON_FLAGS) : '';

        return \rtrim(\sprintf("%s  %s\t<color;fg=green>%s</>\t%s %s", $date, $level, 'phpss', $message, $context));
    }
}
