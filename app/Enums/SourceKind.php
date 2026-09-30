<?php

namespace App\Enums;

enum SourceKind: string
{
    case MetricalRecord = 'metrical_record';
    case Gravestone = 'gravestone';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MetricalRecord => 'akt metrykalny',
            self::Gravestone => 'zdjęcie nagrobka',
            self::Other => 'inne',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::MetricalRecord => 'akt',
            self::Gravestone => 'nagrobek',
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
