<?php

namespace App\Enums;

enum CemeteryManager: string
{
    case Municipal = 'municipal';
    case Religious = 'religious';
    case War = 'war';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Municipal => 'komunalny',
            self::Religious => 'wyznaniowy',
            self::War => 'wojenny',
            self::Other => 'inny',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $manager): array => ['value' => $manager->value, 'label' => $manager->label()],
            self::cases(),
        );
    }
}
