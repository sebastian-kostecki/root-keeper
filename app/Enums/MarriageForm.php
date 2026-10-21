<?php

namespace App\Enums;

enum MarriageForm: string
{
    case Civil = 'civil';
    case Religious = 'religious';
    case CivilReligious = 'civil_religious';

    public function label(): string
    {
        return match ($this) {
            self::Civil => 'cywilny',
            self::Religious => 'wyznaniowy',
            self::CivilReligious => 'cywilno-wyznaniowy',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $form): array => ['value' => $form->value, 'label' => $form->label()],
            self::cases(),
        );
    }
}
