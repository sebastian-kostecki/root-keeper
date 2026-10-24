<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;
use App\Enums\RepoKeyKind;

/**
 * What the repo holds for a key in the form the memory of the last import keeps it: field => normalized text, `null`
 * for an empty field. The field names are the ones of `repo_snapshots.values`.
 */
final class RepoValues
{
    /**
     * @param  string|null  $note  the note block of the person, see {@see note()}
     * @param  bool  $withGender  false leaves the gender out, so that it comes back at the next import
     * @return array<string, string|null>
     */
    public static function person(ResearchPerson $person, ?string $note, bool $withGender = true): array
    {
        $values = [
            'imiona' => PlanImport::cleanText($person->givenNames),
            'nazwisko' => PlanImport::cleanText($person->surname),
        ];

        if ($withGender) {
            $values['plec'] = $person->gender?->value;
        }

        $values['notatka'] = $note;

        return $values;
    }

    /**
     * @return array{data: string|null}
     */
    public static function event(ResearchEvent $event): array
    {
        return ['data' => $event->date?->label()];
    }

    /**
     * The values the repo holds for a key, `null` when the reading has no such key.
     *
     * @return array<string, string|null>|null
     */
    public static function forKey(string $key, ResearchRepoReading $reading): ?array
    {
        $ids = RepoKeys::personIdsOf($key);

        switch (RepoKeys::kindOf($key)) {
            case RepoKeyKind::Person:
                $person = $reading->persons[$ids[0]] ?? null;

                return $person === null ? null : self::person($person, self::note($person, $reading));
            case RepoKeyKind::Birth:
                $event = ($reading->persons[$ids[0]] ?? null)?->birth;

                return $event === null ? null : self::event($event);
            case RepoKeyKind::Death:
                $event = ($reading->persons[$ids[0]] ?? null)?->death;

                return $event === null ? null : self::event($event);
            case RepoKeyKind::Relation:
                return null;
            case RepoKeyKind::Marriage:
                foreach ($reading->marriages as $marriage) {
                    if (RepoKeys::marriage(...$marriage->personIds) === $key) {
                        return self::event($marriage->event);
                    }
                }

                return null;
        }
    }

    /**
     * The block a person's note gets: what the repo says besides the fields of the app, under a header with the repo id.
     * Its lines hold no empty line, so that the block ends at the first empty line of the note.
     *
     * @param  array<string, true>  $skipped  repo ids of persons left out; no wedding line mentions them
     */
    public static function note(ResearchPerson $person, ResearchRepoReading $reading, array $skipped = []): ?string
    {
        $lines = [];

        foreach (['Urodzenie' => $person->birth, 'Zgon' => $person->death] as $label => $event) {
            $details = $event === null ? [] : self::eventDetails($event);

            if ($details !== []) {
                $lines[] = "{$label}: ".implode('; ', $details);
            }
        }

        if ($person->burial !== null) {
            $details = self::eventDetails($person->burial, withDate: true);
            $lines[] = 'Pochowanie: '.($details === [] ? 'bez szczegółów' : implode('; ', $details));
        }

        $marriages = array_filter(
            $reading->marriages,
            fn (ResearchMarriage $marriage): bool => in_array($person->id, $marriage->personIds, true),
        );
        $byPartner = [];

        foreach ($marriages as $marriage) {
            $partnerId = $marriage->personIds[0] === $person->id ? $marriage->personIds[1] : $marriage->personIds[0];

            if (! isset($skipped[$partnerId])) {
                $byPartner[$partnerId] = $marriage;
            }
        }

        ksort($byPartner);

        foreach ($byPartner as $partnerId => $marriage) {
            $details = self::eventDetails($marriage->event);

            if ($details !== []) {
                $lines[] = sprintf('Ślub z %s (%s): %s', isset($reading->persons[$partnerId]) ? self::nameOf($reading->persons[$partnerId]) : 'NN', $partnerId, implode('; ', $details));
            }
        }

        if ($person->surnameVariants !== []) {
            $lines[] = 'Warianty nazwiska: '.implode(', ', $person->surnameVariants);
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n", ["Z repo badawczego ({$person->id}):", ...$lines]);
    }

    /**
     * @return list<string>
     */
    private static function eventDetails(ResearchEvent $event, bool $withDate = false): array
    {
        return array_values(array_filter([
            $withDate ? $event->date?->label() : null,
            $event->place,
            $event->level === null ? null : 'poziom '.$event->level->value,
            $event->note,
        ], fn (?string $part): bool => $part !== null));
    }

    public static function nameOf(ResearchPerson $person): string
    {
        $name = trim(($person->givenNames ?? '').' '.($person->surname ?? ''));

        return $name === '' ? 'NN' : $name;
    }
}
