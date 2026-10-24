<?php

namespace App\Enums;

enum MatchVerdict: string
{
    case New = 'new';
    case Sure = 'sure';
    case Doubtful = 'doubtful';

    public function label(): string
    {
        return match ($this) {
            self::New => 'nowe',
            self::Sure => 'pewne',
            self::Doubtful => 'wątpliwe',
        };
    }
}
