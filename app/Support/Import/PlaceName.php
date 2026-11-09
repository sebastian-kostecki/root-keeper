<?php

namespace App\Support\Import;

/**
 * How the names of places are compared: the key of a name and whether two different keys look like one place.
 */
final class PlaceName
{
    /** The longest key that tolerates one differing character; a longer one tolerates two. */
    private const SHORT_KEY_LENGTH = 6;

    /**
     * The key of a place name: no diacritics and capitals, single spaces; `null` when nothing is left.
     */
    public static function key(?string $name): ?string
    {
        return NameKey::normalize($name);
    }

    /**
     * Whether two place keys look like the same place written differently: within one edit for a short key (up to six
     * characters) or two for a longer one, or the same words in another order. A name that only contains the other
     * ("Siedliszcze" and "Siedliszcze Bramowe") is not similar.
     */
    public static function similar(string $keyA, string $keyB): bool
    {
        if ($keyA === $keyB) {
            return true;
        }

        $shorter = min(strlen($keyA), strlen($keyB));
        $allowed = $shorter <= self::SHORT_KEY_LENGTH ? 1 : 2;

        if (levenshtein($keyA, $keyB) <= $allowed) {
            return true;
        }

        return self::sortedWords($keyA) === self::sortedWords($keyB);
    }

    /**
     * @return list<string>
     */
    private static function sortedWords(string $key): array
    {
        $words = explode(' ', $key);
        sort($words);

        return $words;
    }
}
