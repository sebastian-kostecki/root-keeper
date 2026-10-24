<?php

namespace App\Support\Import;

use Illuminate\Support\Str;

final class NameKey
{
    /**
     * The text without diacritics and capitals, trimmed and with runs of spaces merged; `null` when nothing is left.
     */
    public static function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $key = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($text))));

        return $key === '' ? null : $key;
    }

    /**
     * The key of the first of the given names.
     */
    public static function firstName(?string $givenNames): ?string
    {
        $key = self::normalize($givenNames);

        return $key === null ? null : explode(' ', $key)[0];
    }

    /**
     * The key of a surname field.
     */
    public static function surname(?string $surname): ?string
    {
        return self::normalize($surname);
    }

    /**
     * The surname of a repo person and each of its variants; `null` stands for a missing surname.
     *
     * @return list<string|null>
     */
    public static function surnames(ResearchPerson $person): array
    {
        $keys = [self::surname($person->surname)];

        foreach ($person->surnameVariants as $variant) {
            $key = self::surname($variant);

            if ($key !== null && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
