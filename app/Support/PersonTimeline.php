<?php

namespace App\Support;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Person;

/**
 * The timeline of a person: own events, the birth and death of each child and
 * the death of each spouse, ordered by date. Reads only the relations already
 * loaded by BuildPersonPreview, so it issues no queries. The caller must preload
 * `events.locality`, `children.events.locality` and
 * `marriages.participants.events.locality`; otherwise every row lazy-loads its place.
 */
final class PersonTimeline
{
    /**
     * @return list<TimelineEntry>
     */
    public static function for(Person $person): array
    {
        $entries = [
            ...self::ownEntries($person),
            ...self::childEntries($person),
            ...self::spouseEntries($person),
        ];

        return array_values(collect($entries)
            ->sortBy(fn (TimelineEntry $entry): array => self::sortKey($entry, $person))
            ->all());
    }

    /**
     * @return list<TimelineEntry>
     */
    private static function ownEntries(Person $person): array
    {
        $entries = [];

        $birth = $person->birth();
        if ($birth !== null && self::hasBirthDetails($birth)) {
            $entries[] = new TimelineEntry($birth, 'self', null);
        }

        foreach ($person->marriages as $marriage) {
            $entries[] = new TimelineEntry(
                $marriage,
                'self',
                $marriage->participants->firstWhere('id', '!=', $person->id),
            );
        }

        $death = $person->death();
        if ($death !== null) {
            $entries[] = new TimelineEntry($death, 'self', null);
        }

        $burial = $person->burial();
        if ($burial !== null) {
            $entries[] = new TimelineEntry($burial, 'self', null);
        }

        return $entries;
    }

    /**
     * @return list<TimelineEntry>
     */
    private static function childEntries(Person $person): array
    {
        $entries = [];

        foreach ($person->children as $child) {
            $birth = $child->birth();
            if ($birth !== null && self::hasBirthDetails($birth)) {
                $entries[] = new TimelineEntry($birth, 'child', $child);
            }

            $death = $child->death();
            if ($death !== null) {
                $entries[] = new TimelineEntry($death, 'child', $child);
            }
        }

        return $entries;
    }

    /**
     * @return list<TimelineEntry>
     */
    private static function spouseEntries(Person $person): array
    {
        $entries = [];

        $spouses = $person->marriages
            ->flatMap(fn (Event $marriage): array => $marriage->participants->all())
            ->reject(fn (Person $participant): bool => $participant->id === $person->id)
            ->unique('id');

        foreach ($spouses as $spouse) {
            $death = $spouse->death();
            if ($death !== null) {
                $entries[] = new TimelineEntry($death, 'spouse', $spouse);
            }
        }

        return $entries;
    }

    /**
     * A birth without a year and without a locality carries nothing to show.
     */
    private static function hasBirthDetails(Event $birth): bool
    {
        return $birth->date_year !== null || $birth->locality_id !== null;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int}
     */
    private static function sortKey(TimelineEntry $entry, Person $person): array
    {
        [$year, $month, $day] = self::sortDateParts($entry, $person);

        return [
            $year === null ? 1 : 0,
            $year ?? 0,
            $year === null ? 0 : $month ?? 0,
            $year === null ? 0 : $day ?? 0,
            $entry->rank(),
            $entry->event->id,
        ];
    }

    /**
     * A burial has no date of its own: it sorts by the viewed person's death date,
     * and without a death year it sorts with the undated rows.
     *
     * @return array{0: int|null, 1: int|null, 2: int|null}
     */
    private static function sortDateParts(TimelineEntry $entry, Person $person): array
    {
        if ($entry->event->type !== EventType::Burial) {
            return [$entry->event->date_year, $entry->event->date_month, $entry->event->date_day];
        }

        $death = $person->death();

        if ($death === null || $death->date_year === null) {
            return [null, null, null];
        }

        return [$death->date_year, $death->date_month, $death->date_day];
    }
}
