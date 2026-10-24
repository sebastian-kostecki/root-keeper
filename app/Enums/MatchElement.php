<?php

namespace App\Enums;

enum MatchElement: string
{
    case BirthYear = 'birth_year';
    case BirthPlace = 'birth_place';
    case Father = 'father';
    case Mother = 'mother';
    case Spouse = 'spouse';
    case DeathYear = 'death_year';

    public function label(): string
    {
        return match ($this) {
            self::BirthYear => 'rok urodzenia',
            self::BirthPlace => 'miejsce urodzenia',
            self::Father => 'ojciec',
            self::Mother => 'matka',
            self::Spouse => 'małżonek',
            self::DeathYear => 'rok zgonu',
        };
    }
}
