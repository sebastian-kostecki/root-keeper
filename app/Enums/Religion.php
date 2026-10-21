<?php

namespace App\Enums;

enum Religion: string
{
    case RomanCatholic = 'roman_catholic';
    case GreekCatholic = 'greek_catholic';
    case Evangelical = 'evangelical';
    case Orthodox = 'orthodox';
    case Jewish = 'jewish';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RomanCatholic => 'rzymskokatolickie',
            self::GreekCatholic => 'grekokatolickie',
            self::Evangelical => 'ewangelickie',
            self::Orthodox => 'prawosławne',
            self::Jewish => 'mojżeszowe',
            self::Other => 'inne',
        };
    }

    /**
     * The short form used in parish labels, as in Geneteka.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::RomanCatholic => 'rzym.-kat.',
            self::GreekCatholic => 'gr.-kat.',
            self::Evangelical => 'ewang.',
            self::Orthodox => 'prawosł.',
            self::Jewish => 'mojż.',
            self::Other => 'inne',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $religion): array => ['value' => $religion->value, 'label' => $religion->label()],
            self::cases(),
        );
    }
}
