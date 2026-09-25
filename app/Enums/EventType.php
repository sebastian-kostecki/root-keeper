<?php

namespace App\Enums;

enum EventType: string
{
    case Birth = 'birth';
    case Death = 'death';
    case Marriage = 'marriage';

    public function label(): string
    {
        return match ($this) {
            self::Birth => 'Urodzenie',
            self::Death => 'Zgon',
            self::Marriage => 'Małżeństwo',
        };
    }
}
