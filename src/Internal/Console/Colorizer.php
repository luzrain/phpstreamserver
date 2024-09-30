<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

/**
 * @internal
 */
final class Colorizer
{
    /**
     * @see https://en.wikipedia.org/wiki/ANSI_escape_code#8-bit
     */
    private const COLORMAP = [
        'black' => 0,
        'red' => 1,
        'green' => 2,
        'yellow' => 3,
        'blue' => 4,
        'magenta' => 5,
        'cyan' => 6,
        'white' => 7,
        'gray' => 8,
    ];

    private function __construct()
    {
    }

    /**
     * @param resource $stream
     */
    public static function hasColorSupport($stream): bool
    {
        // Follow https://no-color.org/
        if (\getenv('NO_COLOR') !== false) {
            return false;
        }

        return \getenv('TERM_PROGRAM') === 'Hyper'
            || \getenv('ANSICON') !== false
            || \getenv('ConEmuANSI') === 'ON'
            || \str_starts_with((string) \getenv('TERM'), 'xterm')
            || \stream_isatty($stream);
    }

    /**
     * Remove colorize tags
     */
    public static function stripTags(string $string): string
    {
        return \preg_replace('/<color;.+?>([^<>]*)<\/(?:color)?>/', "$1", $string);
    }

    /**
     * Colorize string in terminal. Usage: <color;fg=green;bg=black>green text</>
     */
    public static function colorize(string $string): string
    {
        \preg_match_all('/<color;(.+)>.+<\/(?:color)?>/U', $string, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            \parse_str(\str_replace(';', '&', $match[1] ?? ''), $attr);
            /** @var int $pos */
            $pos = \strpos($string, $match[0]);
            $len = \strlen($match[0]);
            $text = \strip_tags($match[0]);
            $color = self::COLORMAP[$attr['fg'] ?? $attr['bg']] ?? $attr['fg'] ?? $attr['bg'];
            $isFg = isset($attr['fg']);
            $formattedString = \sprintf("\e[%s:5:%sm%s\e[0m", $isFg ? '38' : '48', $color, $text);
            $string = \substr_replace($string, $formattedString, $pos, $len);
        }

        return self::stripTags($string);
    }
}
