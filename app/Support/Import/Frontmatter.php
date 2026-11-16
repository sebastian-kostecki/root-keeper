<?php

namespace App\Support\Import;

/**
 * The YAML block at the top of a research repo file: shared by the person and the source parsers.
 */
final class Frontmatter
{
    /**
     * The contents without a byte order mark and with unix line endings.
     */
    public static function normalize(string $contents): string
    {
        return str_replace("\r\n", "\n", preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents);
    }

    /**
     * Whether the (normalized) contents open with the `---` line of a block.
     */
    public static function starts(string $contents): bool
    {
        return preg_match('/\A---[ \t]*\n/', $contents) === 1;
    }

    /**
     * The YAML between the first `---` line and the closing one, `null` when the block is not closed.
     */
    public static function extract(string $contents): ?string
    {
        $body = preg_replace('/\A---[ \t]*\n/', '', $contents);

        if (preg_match('/^---[ \t]*$/m', (string) $body, $closing, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return substr((string) $body, 0, $closing[0][1]);
    }
}
