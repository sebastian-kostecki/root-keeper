<?php

namespace App\Actions\Import;

use App\Enums\Gender;
use App\Models\Tree;
use App\Support\Import\FirstImportPlan;
use App\Support\Import\ImportIssue;
use App\Support\Import\NoteValueCounts;
use App\Support\Import\PlannedMarriage;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedRelation;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchMarriage;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;

class PlanFirstImport
{
    private const BLOCKED_REASON = 'Drzewo konta ma już osoby. Import do drzewa z osobami (dopasowanie — S-03, ponowny import — S-04) jeszcze nie działa; nic nie zostanie zapisane.';

    /**
     * Decide what a first import writes into the tree, without writing anything.
     */
    public function handle(Tree $tree, ResearchRepoReading $reading): FirstImportPlan
    {
        [$relations, $skipped] = $this->planRelations($reading->persons);

        $persons = [];
        $births = 0;
        $deaths = 0;
        $dated = [];
        $undated = 0;
        $places = 0;
        $burials = 0;
        $levels = 0;
        $eventNotes = 0;
        $variants = 0;

        foreach ($reading->persons as $person) {
            $persons[] = new PlannedPerson(
                $person,
                $this->noteFor($person, $reading),
                $person->birth === null ? null : $person->id.'/urodzenie',
                $person->death === null ? null : $person->id.'/zgon',
            );

            $variants += count($person->surnameVariants);

            if ($person->birth !== null) {
                $births++;
            }

            if ($person->death !== null) {
                $deaths++;
            }

            if ($person->burial !== null) {
                $burials++;
            }

            foreach ([$person->birth, $person->death] as $event) {
                $this->tally($event, $dated, $undated);
            }

            foreach ([$person->birth, $person->death, $person->burial] as $event) {
                $this->tallyNoteValues($event, $places, $levels, $eventNotes);
            }
        }

        $marriages = [];

        foreach ($reading->marriages as $marriage) {
            $marriages[] = new PlannedMarriage($marriage, implode('+', $marriage->personIds).'/slub');
            $this->tally($marriage->event, $dated, $undated);
            $this->tallyNoteValues($marriage->event, $places, $levels, $eventNotes);
        }

        ksort($dated);

        return new FirstImportPlan(
            persons: $persons,
            relations: $relations,
            marriages: $marriages,
            skippedRelations: $skipped,
            births: $births,
            deaths: $deaths,
            weddings: count($marriages),
            datesByQualifier: $dated,
            undatedEvents: $undated,
            noteValues: new NoteValueCounts($places, $burials, $levels, $eventNotes, $variants),
            probandHint: $this->probandHint($reading),
            blockedReason: $tree->people()->exists() ? self::BLOCKED_REASON : null,
        );
    }

    /**
     * @param  array<string, ResearchPerson>  $persons
     * @return array{0: list<PlannedRelation>, 1: list<ImportIssue>}
     */
    private function planRelations(array $persons): array
    {
        $skipped = [];
        /** @var list<array{0: ResearchPerson, 1: ResearchPerson}> $candidates */
        $candidates = [];

        foreach ($persons as $child) {
            if ($child->fatherId !== null && $child->fatherId === $child->motherId && isset($persons[$child->fatherId])) {
                foreach (['ojciec', 'matka'] as $role) {
                    $skipped[] = new ImportIssue($child->file, "relacja pominięta: ten sam id jako ojciec i matka ({$role}: {$child->fatherId})");
                }

                continue;
            }

            foreach (['ojciec' => [$child->fatherId, Gender::Female], 'matka' => [$child->motherId, Gender::Male]] as $role => [$parentId, $wrongGender]) {
                $parent = $parentId === null ? null : ($persons[$parentId] ?? null);

                if ($parent === null) {
                    continue;
                }

                if ($parent->gender === $wrongGender) {
                    $skipped[] = new ImportIssue($child->file, "relacja pominięta: płeć rodzica niezgodna z rolą ({$role}: {$parent->id})");

                    continue;
                }

                $candidates[] = [$parent, $child];
            }
        }

        $component = $this->componentsOf($candidates);
        $size = array_count_values($component);
        $members = [];

        foreach ($component as $id => $index) {
            $members[$index][] = $id;
        }

        $relations = [];

        foreach ($candidates as [$parent, $child]) {
            $index = $component[$parent->id];
            $onCycle = $parent->id === $child->id || ($index === $component[$child->id] && $size[$index] > 1);

            if ($onCycle) {
                $ids = $members[$index];
                sort($ids);
                $skipped[] = new ImportIssue($child->file, sprintf(
                    'relacja pominięta: %s → %s leży na cyklu rodzic–dziecko (%s)',
                    $parent->id,
                    $child->id,
                    implode(', ', array_map(fn (string $id): string => "{$id} {$persons[$id]->file}", $ids)),
                ));

                continue;
            }

            $relations[] = new PlannedRelation($parent->id, $child->id, $parent->id.'>'.$child->id);
        }

        return [$relations, $skipped];
    }

    /**
     * Strongly connected components (Tarjan) of the parent → child graph, as repo id → component index.
     *
     * @param  list<array{0: ResearchPerson, 1: ResearchPerson}>  $edges
     * @return array<string, int>
     */
    private function componentsOf(array $edges): array
    {
        $graph = [];

        foreach ($edges as [$parent, $child]) {
            $graph[$parent->id][] = $child->id;
            $graph[$child->id] ??= [];
        }

        $index = 0;
        $number = [];
        $low = [];
        $onStack = [];
        $stack = [];
        $component = [];
        $count = 0;

        $visit = function (string $node) use (&$visit, &$index, &$number, &$low, &$onStack, &$stack, &$component, &$count, $graph): void {
            $number[$node] = $low[$node] = $index++;
            $stack[] = $node;
            $onStack[$node] = true;

            foreach ($graph[$node] as $next) {
                if (! isset($number[$next])) {
                    $visit($next);
                    $low[$node] = min($low[$node], $low[$next]);
                } elseif ($onStack[$next] ?? false) {
                    $low[$node] = min($low[$node], $number[$next]);
                }
            }

            if ($low[$node] === $number[$node]) {
                do {
                    $member = array_pop($stack);
                    $onStack[$member] = false;
                    $component[$member] = $count;
                } while ($member !== $node);

                $count++;
            }
        };

        foreach (array_keys($graph) as $node) {
            if (! isset($number[$node])) {
                $visit($node);
            }
        }

        return $component;
    }

    /**
     * @param  array<string, int>  $dated
     */
    private function tally(?ResearchEvent $event, array &$dated, int &$undated): void
    {
        if ($event === null) {
            return;
        }

        if ($event->date === null) {
            $undated++;

            return;
        }

        $key = $event->date->qualifier->value;
        $dated[$key] = ($dated[$key] ?? 0) + 1;
    }

    private function tallyNoteValues(?ResearchEvent $event, int &$places, int &$levels, int &$eventNotes): void
    {
        if ($event === null) {
            return;
        }

        $places += $event->place === null ? 0 : 1;
        $levels += $event->level === null ? 0 : 1;
        $eventNotes += $event->note === null ? 0 : 1;
    }

    private function probandHint(ResearchRepoReading $reading): ?ResearchPerson
    {
        $holders = array_values(array_filter(
            $reading->persons,
            fn (ResearchPerson $person): bool => in_array(1, $person->ahnentafel, true),
        ));

        return count($holders) === 1 ? $holders[0] : null;
    }

    private function noteFor(ResearchPerson $person, ResearchRepoReading $reading): ?string
    {
        $lines = [];

        foreach (['Urodzenie' => $person->birth, 'Zgon' => $person->death] as $label => $event) {
            $details = $event === null ? [] : $this->eventDetails($event);

            if ($details !== []) {
                $lines[] = "{$label}: ".implode('; ', $details);
            }
        }

        if ($person->burial !== null) {
            $details = $this->eventDetails($person->burial, withDate: true);
            $lines[] = 'Pochowanie: '.($details === [] ? 'bez szczegółów' : implode('; ', $details));
        }

        $marriages = array_filter(
            $reading->marriages,
            fn (ResearchMarriage $marriage): bool => in_array($person->id, $marriage->personIds, true),
        );
        $byPartner = [];

        foreach ($marriages as $marriage) {
            $partnerId = $marriage->personIds[0] === $person->id ? $marriage->personIds[1] : $marriage->personIds[0];
            $byPartner[$partnerId] = $marriage;
        }

        ksort($byPartner);

        foreach ($byPartner as $partnerId => $marriage) {
            $details = $this->eventDetails($marriage->event);

            if ($details !== []) {
                $lines[] = sprintf('Ślub z %s (%s): %s', isset($reading->persons[$partnerId]) ? $this->nameOf($reading->persons[$partnerId]) : 'NN', $partnerId, implode('; ', $details));
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
    private function eventDetails(ResearchEvent $event, bool $withDate = false): array
    {
        return array_values(array_filter([
            $withDate ? $event->date?->label() : null,
            $event->place,
            $event->level === null ? null : 'poziom '.$event->level->value,
            $event->note,
        ], fn (?string $part): bool => $part !== null));
    }

    private function nameOf(ResearchPerson $person): string
    {
        $name = trim(($person->givenNames ?? '').' '.($person->surname ?? ''));

        return $name === '' ? 'NN' : $name;
    }
}
