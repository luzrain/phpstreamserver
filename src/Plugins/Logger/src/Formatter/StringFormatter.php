<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\BundledPlugin\Logger\Formatter;

use Luzrain\PHPStreamServer\BundledPlugin\Logger\FormatterInterface;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\FlattenNormalizer\FlattenDateTime;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\FlattenNormalizer\FlattenEnum;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\FlattenNormalizer\FlattenException;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\FlattenNormalizer\FlattenObject;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\FlattenNormalizer\FlattenResource;
use Luzrain\PHPStreamServer\BundledPlugin\Logger\Internal\LogEntry;

/**
 * Formats a log records into string
 */
final readonly class StringFormatter implements FormatterInterface
{
    private const DEFAULT_FORMAT = '[{time}] {channel}.{level} {message} {context}';

    public const DEFAULT_JSON_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
        | JSON_FORCE_OBJECT
    ;

    public function __construct(
        private string $messageFormat = self::DEFAULT_FORMAT,
        private string $dateTimeFormat = \DateTimeInterface::RFC3339,
    ) {
    }

    public function format(LogEntry $record): string
    {
        return \trim(\strtr($this->messageFormat, [
            '{time}' => $record->time->format($this->dateTimeFormat),
            '{channel}' => $record->channel,
            '{level}' => \strtoupper($record->level->toString()),
            '{message}' => $record->message,
            '{context}' => $record->context === [] ? '' : \json_encode($this->normalize($record->context), self::DEFAULT_JSON_FLAGS),
        ]));
    }

    private function normalize(mixed $data): mixed
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->normalize($value);
            }

            return $data;
        }

        if (\is_null($data) || \is_scalar($data)) {
            return $data;
        }

        if ($data instanceof FlattenException) {
            return $data->toString();
        }

        if ($data instanceof FlattenDateTime) {
            return $data->format($this->dateTimeFormat);
        }

        if ($data instanceof FlattenObject) {
            return $data->toString();
        }

        if ($data instanceof FlattenResource) {
            return $data->toString();
        }

        if ($data instanceof FlattenEnum) {
            return $data->toString();
        }

        return 'unknown';
    }
}
