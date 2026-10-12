<?php

namespace App\Enums;

enum EventType: string
{
    case Birth = 'birth';
    case Death = 'death';
    case Marriage = 'marriage';
    case Burial = 'burial';

    public function label(): string
    {
        return match ($this) {
            self::Birth => 'Urodzenie',
            self::Death => 'Zgon',
            self::Marriage => 'Małżeństwo',
            self::Burial => 'Pochowanie',
        };
    }

    /**
     * Whether a source can confirm this event (FR-025: birth, marriage and death only).
     */
    public function acceptsSources(): bool
    {
        return $this !== self::Burial;
    }
}
