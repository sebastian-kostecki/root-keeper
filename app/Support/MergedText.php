<?php

namespace App\Support;

class MergedText
{
    /**
     * The target's text, extended by the duplicate's one when the target has none or they differ.
     */
    public static function of(?string $targetValue, ?string $duplicateValue): ?string
    {
        $targetText = trim((string) $targetValue);
        $duplicateText = trim((string) $duplicateValue);

        if ($duplicateText === '' || $targetText === $duplicateText) {
            return $targetValue;
        }

        if ($targetText === '') {
            return $duplicateValue;
        }

        return $targetText."\n\n".$duplicateText;
    }
}
