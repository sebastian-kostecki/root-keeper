<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A person's age computed from partial dates, in the largest unit that fits.
 */
final readonly class Age
{
    private const int LIVING_AGE_LIMIT = 110;

    /**
     * @param  'years'|'months'|'days'|'under_year'  $unit
     */
    public function __construct(
        public string $unit,
        public ?int $value,
    ) {}

    /**
     * @param  bool  $isDeceased  Whether the person has a death event; without one the period ends today.
     */
    public static function from(?PartialDate $birth, ?PartialDate $death, bool $isDeceased, CarbonImmutable $today): ?self
    {
        if ($birth === null || $birth->year === null) {
            return null;
        }

        if ($isDeceased) {
            if ($death === null || $death->year === null) {
                return null;
            }

            $end = $death;
        } else {
            $end = new PartialDate($today->year, $today->month, $today->day, false);
        }

        $hasMonths = $birth->month !== null && $end->month !== null;
        $isFull = $hasMonths && $birth->day !== null && $end->day !== null;

        if ($isFull) {
            return self::fromFullDates($birth, $end, $isDeceased);
        }

        $years = $end->year - $birth->year;

        if ($hasMonths && $end->month < $birth->month) {
            $years--;
        }

        if ($years < 0) {
            return null;
        }

        if ($years === 0) {
            return new self('under_year', null);
        }

        return self::years($years, $isDeceased);
    }

    private static function fromFullDates(PartialDate $birth, PartialDate $end, bool $isDeceased): ?self
    {
        $from = CarbonImmutable::create($birth->year, $birth->month, $birth->day, 0, 0, 0, 'UTC');
        $to = CarbonImmutable::create($end->year, $end->month, $end->day, 0, 0, 0, 'UTC');
        $interval = $from->diff($to);

        if ($interval->invert === 1) {
            return null;
        }

        if ($interval->y >= 1) {
            return self::years($interval->y, $isDeceased);
        }

        if ($interval->m >= 1) {
            return new self('months', $interval->m);
        }

        return new self('days', $interval->d);
    }

    private static function years(int $years, bool $isDeceased): ?self
    {
        if (! $isDeceased && $years >= self::LIVING_AGE_LIMIT) {
            return null;
        }

        return new self('years', $years);
    }

    /**
     * @return array{unit: 'years'|'months'|'days'|'under_year', value: int|null}
     */
    public function toArray(): array
    {
        return ['unit' => $this->unit, 'value' => $this->value];
    }
}
