<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Person;
use App\Models\Residence;
use Illuminate\Support\Collection;

/**
 * The sort keys shared by the person preview and the family sheet, so both
 * order relatives and marriages identically.
 */
final class PersonOrdering
{
    /**
     * Order relatives by birth date (known year first, then year/month/day
     * ascending with a missing month/day sorting before a known one within
     * the same year), then by name.
     *
     * @param  Collection<int, Person>  $relatives
     * @return Collection<int, Person>
     */
    public static function relatives(Collection $relatives): Collection
    {
        return $relatives->sort(
            fn (Person $a, Person $b): int => self::relativeSortKey($a) <=> self::relativeSortKey($b)
        )->values();
    }

    /**
     * Order marriages by date (known year first, then year/month/day
     * ascending with a missing month/day sorting before a known one within
     * the same year), then by id.
     *
     * @param  Collection<int, Event>  $marriages
     * @return Collection<int, Event>
     */
    public static function marriages(Collection $marriages): Collection
    {
        return $marriages->sort(
            fn (Event $a, Event $b): int => self::marriageSortKey($a) <=> self::marriageSortKey($b)
        )->values();
    }

    /**
     * Order residences by year (year_from, else year_to) ascending, entries
     * without any year last, ties by id (order of adding).
     *
     * @param  Collection<int, Residence>  $residences
     * @return Collection<int, Residence>
     */
    public static function residences(Collection $residences): Collection
    {
        return $residences->sortBy(function (Residence $residence): array {
            $year = $residence->year_from ?? $residence->year_to;

            return [$year === null ? 1 : 0, $year ?? 0, $residence->id];
        })->values();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: string, 6: string, 7: int}
     */
    private static function relativeSortKey(Person $person): array
    {
        $date = $person->birth()?->date;

        return [
            $date?->year === null ? 1 : 0,
            $date->year ?? 0,
            $date->month ?? 0,
            $date->day ?? 0,
            $person->last_name === null ? 1 : 0,
            $person->last_name ?? '',
            $person->first_name ?? '',
            $person->id,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private static function marriageSortKey(Event $marriage): array
    {
        return [
            $marriage->date_year === null ? 1 : 0,
            $marriage->date_year ?? 0,
            $marriage->date_month ?? 0,
            $marriage->date_day ?? 0,
            $marriage->id,
        ];
    }
}
