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

        $values['warianty'] = SurnameVariants::fromResearch($person)->text();
        $values['notatka'] = $note;

        return $values;
    }

    /**
     * The memory of an event: the date and the place text. A burial also keeps the cemetery name. The ids of the
     * recognized place (`miejsce_id`, `cmentarz_id`) come from the planner, which knows the tree.
     *
     * @param  bool  $burial  true adds the `cmentarz` field
     * @return array{data: string|null, miejsce: string|null, cmentarz?: string|null}
     */
    public static function event(ResearchEvent $event, bool $burial = false): array
    {
        $values = [
            'data' => $event->date?->label(),
            'miejsce' => PlanImport::cleanText($event->place),
        ];

        if ($burial) {
            $values['cmentarz'] = PlanImport::cleanText($event->cemetery);
        }

        return $values;
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
            case RepoKeyKind::Burial:
                $event = ($reading->persons[$ids[0]] ?? null)?->burial;

                return $event === null ? null : self::event($event, burial: true);
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
     * An event whose place the tree holds after the import does not repeat the place text. A burial line stays only
     * when it has something to say.
     *
     * @param  array<string, true>  $skipped  repo ids of persons left out; no wedding line mentions them
     * @param  array<string, true>  $placed  repo keys of the events (and weddings) that have the place the repo names after the import
     */
    public static function note(ResearchPerson $person, ResearchRepoReading $reading, array $skipped = [], array $placed = []): ?string
    {
        $lines = [];

        foreach (['Urodzenie' => [$person->birth, RepoKeys::birth($person->id)], 'Zgon' => [$person->death, RepoKeys::death($person->id)]] as $label => [$event, $key]) {
            $details = $event === null ? [] : self::eventDetails($event, placed: isset($placed[$key]));

            if ($details !== []) {
                $lines[] = "{$label}: ".implode('; ', $details);
            }
        }

        if ($person->burial !== null) {
            $details = self::eventDetails($person->burial, withDate: true, placed: isset($placed[RepoKeys::burial($person->id)]));

            if ($details !== []) {
                $lines[] = 'Pochowanie: '.implode('; ', $details);
            }
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
            $details = self::eventDetails($marriage->event, placed: isset($placed[RepoKeys::marriage(...$marriage->personIds)]));

            if ($details !== []) {
                $lines[] = sprintf('Ślub z %s (%s): %s', isset($reading->persons[$partnerId]) ? self::nameOf($reading->persons[$partnerId]) : 'NN', $partnerId, implode('; ', $details));
            }
        }

        if ($lines === []) {
            return null;
        }

        return implode("\n", ["Z repo badawczego ({$person->id}):", ...$lines]);
    }

    /**
     * @return list<string>
     */
    private static function eventDetails(ResearchEvent $event, bool $withDate = false, bool $placed = false): array
    {
        return array_values(array_filter([
            $withDate ? $event->date?->label() : null,
            $placed ? null : $event->place,
            $placed ? null : $event->cemetery,
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
