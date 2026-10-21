<?php

namespace App\Enums;

enum ParishKind: string
{
    case Parish = 'parish';
    case CivilRegistry = 'civil_registry';
    case JewishDistrict = 'jewish_district';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Parish => 'parafia',
            self::CivilRegistry => 'USC / Standesamt',
            self::JewishDistrict => 'okręg bóżniczy / metrykalny',
            self::Other => 'inny',
        };
    }

    /**
     * The word that follows the seat in the label; a parish and an other kind have none.
     */
    public function labelSuffix(): ?string
    {
        return match ($this) {
            self::Parish, self::Other => null,
            self::CivilRegistry => 'USC',
            self::JewishDistrict => 'okręg bóżniczy',
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
