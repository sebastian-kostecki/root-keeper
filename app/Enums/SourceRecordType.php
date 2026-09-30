<?php

namespace App\Enums;

enum SourceRecordType: string
{
    case Birth = 'birth';
    case Marriage = 'marriage';
    case Death = 'death';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Birth => 'urodzenia',
            self::Marriage => 'małżeństwa',
            self::Death => 'zgonu',
            self::Other => 'inny',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
