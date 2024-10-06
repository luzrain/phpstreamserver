<?php

declare(strict_types=1);

namespace Luzrain\PHPStreamServer\Internal\Console;

/**
 * @internal
 */
final class IOStreamFilter extends \php_user_filter
{
    public const NAME = 'phpss.iostream';

    public static bool $enableColors = true;
    public static bool $enableOutput = true;

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = \stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;

            if (!self::$enableOutput) {
                continue;
            }

            $colorize = self::$enableColors && Colorizer::hasColorSupport($this->stream);
            $text = &$bucket->data;
            $text = $colorize ? Colorizer::colorize($text) : Colorizer::stripTags($text);

            \stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
