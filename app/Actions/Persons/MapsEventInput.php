<?php

namespace App\Actions\Persons;

/**
 * Shared mapping from the birth/death form input shape to event fillable
 * attributes, plus checkbox coercion, so create and update actions cannot
 * diverge.
 */
trait MapsEventInput
{
    /**
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, place?: string|null}  $date
     * @return array{date_year: int|string|null, date_month: int|string|null, date_day: int|string|null, date_is_approximate: bool, place: string|null}
     */
    private function eventAttributesFrom(array $date): array
    {
        return [
            'date_year' => $date['year'] ?? null,
            'date_month' => $date['month'] ?? null,
            'date_day' => $date['day'] ?? null,
            'date_is_approximate' => $this->isChecked($date['is_approximate'] ?? null),
            'place' => $date['place'] ?? null,
        ];
    }

    private function isChecked(bool|int|string|null $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
