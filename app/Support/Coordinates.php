<?php

namespace App\Support;

class Coordinates
{
    /**
     * Turn pasted "latitude, longitude" text (decimal numbers with a dot, separated by a comma, a semicolon
     * and/or whitespace) into a pair rounded to six places; null for any other shape or an out-of-range value.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function parse(string $text): ?array
    {
        $number = '([+-]?\d+(?:\.\d+)?)';

        if (preg_match('/^\s*'.$number.'(?:\s*[,;]\s*|\s+)'.$number.'\s*$/', $text, $matches) !== 1) {
            return null;
        }

        $latitude = round((float) $matches[1], 6);
        $longitude = round((float) $matches[2], 6);

        if (abs($latitude) > 90 || abs($longitude) > 180) {
            return null;
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }
}
