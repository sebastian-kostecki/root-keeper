<?php

namespace App\Enums;

enum LocalityKind: string
{
    case City = 'city';
    case Village = 'village';
    case Colony = 'colony';
    case Settlement = 'settlement';
    case Hamlet = 'hamlet';
    case Manor = 'manor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::City => 'miasto',
            self::Village => 'wieś',
            self::Colony => 'kolonia',
            self::Settlement => 'osada',
            self::Hamlet => 'przysiółek',
            self::Manor => 'folwark / majątek',
            self::Other => 'inne',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
