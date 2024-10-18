<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Plugin\Logger\Formatter;

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\LogRecord;

final class ConsoleFormatter extends LineFormatter
{
    private const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';

    private const LEVEL_COLOR_MAP = [
        Level::Debug->value => 'fg=15',
        Level::Info->value => 'fg=116',
        Level::Notice->value => 'fg=38',
        Level::Warning->value => 'fg=yellow',
        Level::Error->value => 'fg=red',
        Level::Critical->value => 'fg=red',
        Level::Alert->value => 'fg=red',
        Level::Emergency->value => 'bg=red',
    ];

    public function __construct(private string $dateTimeFormat = self::DEFAULT_DATETIME_FORMAT, $includeStacktraces = false)
    {
        parent::__construct(includeStacktraces: $includeStacktraces);
    }

    public function format(LogRecord $record): string
    {
        $context = $this->normalize($record->context);
        $extra = $this->normalize($record->extra);

        $contextArray = [...$context, ...$extra];

        $formatted = \sprintf(
            "%s  <color;%s>%s</>  <color;fg=green>%s</>\t%s %s",
            $record->datetime->format($this->dateTimeFormat),
            self::LEVEL_COLOR_MAP[$record->level->value],
            \str_pad($record->level->getName(), 8),
            $record->channel,
            $record->message,
            $contextArray === [] ? '' : $this->stringify($contextArray),
        );

        return \rtrim($formatted);
    }
}
