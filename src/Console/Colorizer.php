<?php

declare(strict_types=1);

namespace Luzrain\PhpRunner\Console;

final class Colorizer
{
    /**
     * Color name => [foreground code, background code]
     */
    private const COLORMAP = [
        'black' => ['30', '40'],
        'red' => ['31', '41'],
        'green' => ['32', '42'],
        'yellow' => ['33', '43'],
        'blue' => ['34', '44'],
        'magenta' => ['35', '45'],
        'cyan' => ['36', '46'],
        'white' => ['37', '47'],
        'gray' => ['90', '100'],
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
     * Colorize string in terminal. Usage: <color;fg=green>green text</>
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
            $fg = $attr['fg'] ?? null;
            $bg = $attr['bg'] ?? null;
            $colors = [];
            if (isset(self::COLORMAP[$fg][0])) {
                $colors[] = self::COLORMAP[$fg][0];
            }
            if (isset(self::COLORMAP[$bg][1])) {
                $colors[] = self::COLORMAP[$bg][1];
            }
            if (empty($colors)) {
                continue;
            }
            $formattedString = \sprintf("\e[%sm%s\e[0m", \implode(';', $colors), $text);
            $string = \substr_replace($string, $formattedString, $pos, $len);
        }

        return self::stripTags($string);
    }
}
