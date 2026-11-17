<?php

namespace App\Support\Import;

/**
 * The block "Z repo badawczego (O-…):" inside a person's note: a header line and the lines under it up to the first
 * empty line. The rest of the note belongs to the author and is never touched.
 */
final class NoteBlock
{
    /**
     * The block of the repo person in the note, with `\n` line endings, or `null` when the note has none.
     */
    public static function find(?string $note, string $repoId): ?string
    {
        $range = self::locate($note, $repoId);

        if ($note === null || $range === null) {
            return null;
        }

        return str_replace("\r\n", "\n", substr($note, $range[0], $range[1] - $range[0]));
    }

    /**
     * Put `$block` in the place of the repo person's block, append it after an empty line when the note has none, or
     * remove the block together with the empty line before it when `$block` is `null`. An emptied note is `null`.
     */
    public static function replace(?string $note, string $repoId, ?string $block): ?string
    {
        $range = self::locate($note, $repoId);

        if ($range === null) {
            if ($block === null) {
                return $note;
            }

            return trim((string) $note) === '' ? $block : $note."\n\n".$block;
        }

        $before = substr((string) $note, 0, $range[0]);
        $after = substr((string) $note, $range[1]);

        if ($block !== null) {
            return $before.$block.$after;
        }

        $before = rtrim($before);

        if ($before === '') {
            $after = ltrim($after);
        }

        $result = $before.$after;

        return trim($result) === '' ? null : $result;
    }

    /**
     * The start and the end offset of the block.
     *
     * @return array{0: int, 1: int}|null
     */
    private static function locate(?string $note, string $repoId): ?array
    {
        if ($note === null) {
            return null;
        }

        $header = preg_quote("Z repo badawczego ({$repoId}):", '/');

        if (preg_match("/^{$header}[ \\t]*\\r?\$/m", $note, $found, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $start = $found[0][1];

        if (preg_match('/\r?\n[ \t]*(?:\r?\n|\z)/', $note, $blank, PREG_OFFSET_CAPTURE, $start) !== 1) {
            return [$start, strlen($note)];
        }

        return [$start, $blank[0][1]];
    }
}
