<?php

namespace App\Actions\Import;

use App\Enums\EventType;
use App\Enums\Gender;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ImportPlan;
use App\Support\Import\NoteValueCounts;
use App\Support\Import\PlannedFill;
use App\Support\Import\PlannedMarriage;
use App\Support\Import\PlannedMatch;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedRelation;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\SharedParentGraph;
use App\Support\Import\ValueConflict;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PlanImport
{
    /**
     * Until a later import phase lifts it, a tree that already holds a person from an import cannot take another import.
     */
    public const BLOCKED_REASON = 'Drzewo konta ma już osoby z importu. Ponowny import (S-04) jeszcze nie działa; nic nie zostanie zapisane.';

    /**
     * The fields of a matched person in the order they are listed.
     */
    private const FIELDS = ['first_name', 'last_name', 'gender', 'birth_date', 'death_date'];

    /**
     * Decide what an import writes into the tree, without writing anything.
     *
     * Every repo person is new, matched to a person of the tree or skipped, as the decisions say. Parent-child pairs
     * are checked on one graph made of the tree's pairs and the planned ones, so that the write never meets a rule
     * of the app. A value the repo would put into an empty field is a fill, a different one is a conflict.
     *
     * @throws InvalidArgumentException when the decisions do not cover each repo person once or point outside the tree
     */
    public function handle(Tree $tree, ResearchRepoReading $reading, IdentityDecisions $decisions): ImportPlan
    {
        $matchedPeople = $this->matchedPeople($tree, $reading, $decisions);

        $persons = $reading->persons;
        ksort($persons);

        $skippedIds = [];
        $nodes = [];

        foreach ($persons as $repoId => $person) {
            if ($decisions->isSkipped($repoId)) {
                $skippedIds[$repoId] = true;
            } else {
                $nodes[$repoId] = isset($decisions->matched[$repoId]) ? SharedParentGraph::node($decisions->matched[$repoId]) : $repoId;
            }
        }

        [$graph, $genders, $labels] = $this->sharedGraph($tree, $persons, $nodes, $decisions);

        $skippedValues = [];
        $outcomes = [];
        $pendingGenders = [];
        $skippedGenders = [];

        foreach ($persons as $repoId => $research) {
            if (! isset($decisions->matched[$repoId])) {
                continue;
            }

            $person = $matchedPeople[$decisions->matched[$repoId]];
            $outcomes[$repoId] = $this->compareMatch($research, $person, $nodes[$repoId], $graph, $genders, $labels, $skippedValues, $pendingGenders, $skippedGenders);
        }

        [$relations, $skippedRelations] = $this->planRelations($persons, $nodes, $graph, $genders, $labels, $decisions, $matchedPeople);

        foreach ($pendingGenders as $repoId => [$node, $repoGender, $appGender]) {
            $offender = $this->sameSexOffender($graph, $genders, $node, $repoGender, false);

            if ($offender !== null) {
                $skippedValues[] = $this->genderSkipped($persons[$repoId], $repoGender, $offender, $labels);
                $skippedGenders[$repoId] = true;

                continue;
            }

            $genders[$node] = $repoGender;
            $outcomes[$repoId]['gender'] = new ValueConflict(
                "{$repoId}/plec",
                $this->personLabel($persons[$repoId]),
                'gender',
                'Płeć',
                $appGender->label(),
                $repoGender->label(),
                $repoGender,
            );
        }

        $newPersons = [];
        $matched = [];
        $skippedPersons = [];
        $conflicts = [];
        $snapshots = [];
        $totals = new class
        {
            public int $births = 0;

            public int $deaths = 0;

            public int $places = 0;

            public int $burials = 0;

            public int $levels = 0;

            public int $eventNotes = 0;

            public int $variants = 0;

            /** @var array<string, int> */
            public array $dated = [];

            public int $undated = 0;
        };

        foreach ($persons as $repoId => $research) {
            if (isset($skippedIds[$repoId])) {
                $skippedPersons[] = new ImportIssue($research->file, 'osoba pominięta przy dopasowaniu');

                continue;
            }

            $note = RepoValues::note($research, $reading, $skippedIds);
            $snapshots += $this->snapshotsOf($research, $note, ! isset($skippedGenders[$repoId]));
            $totals->variants += count($research->surnameVariants);
            $totals->burials += $research->burial === null ? 0 : 1;

            foreach ([$research->birth, $research->death, $research->burial] as $event) {
                $this->tallyNoteValues($event, $totals->places, $totals->levels, $totals->eventNotes);
            }

            if (! isset($decisions->matched[$repoId])) {
                $newPersons[] = new PlannedPerson(
                    $research,
                    $note,
                    $research->birth === null ? null : RepoKeys::birth($research->id),
                    $research->death === null ? null : RepoKeys::death($research->id),
                );

                foreach ([$research->birth, $research->death] as $event) {
                    $this->tally($event, $totals->dated, $totals->undated);
                }

                $totals->births += $research->birth === null ? 0 : 1;
                $totals->deaths += $research->death === null ? 0 : 1;

                continue;
            }

            $person = $matchedPeople[$decisions->matched[$repoId]];
            $createBirth = $research->birth !== null && $person->birth() === null;
            $createDeath = $research->death !== null && $person->death() === null;
            $fills = [];
            $personConflicts = [];

            foreach (self::FIELDS as $field) {
                $outcome = $outcomes[$repoId][$field] ?? null;

                if ($outcome instanceof PlannedFill) {
                    $fills[] = $outcome;
                } elseif ($outcome instanceof ValueConflict) {
                    $personConflicts[] = $outcome;
                }
            }

            $matched[] = new PlannedMatch(
                $research,
                $person->id,
                $note,
                $research->birth === null ? null : RepoKeys::birth($research->id),
                $research->death === null ? null : RepoKeys::death($research->id),
                $createBirth,
                $createDeath,
                $fills,
                $personConflicts,
            );
            array_push($conflicts, ...$personConflicts);

            if ($createBirth) {
                $totals->births++;
                $this->tally($research->birth, $totals->dated, $totals->undated);
            }

            if ($createDeath) {
                $totals->deaths++;
                $this->tally($research->death, $totals->dated, $totals->undated);
            }
        }

        [$marriages, $skippedMarriages, $marriageConflicts] = $this->planMarriages($reading, $decisions, $matchedPeople, $skippedIds, $persons);
        array_push($conflicts, ...$marriageConflicts);

        foreach ($relations as $planned) {
            $snapshots[$planned->repoId] = [];
        }

        $weddings = 0;

        foreach ($marriages as $planned) {
            $snapshots[$planned->repoId] = ['data' => $planned->marriage->event->date?->label()];
            $this->tallyNoteValues($planned->marriage->event, $totals->places, $totals->levels, $totals->eventNotes);

            if ($planned->existingEventId === null) {
                $weddings++;
                $this->tally($planned->marriage->event, $totals->dated, $totals->undated);
            }
        }

        ksort($totals->dated);

        return new ImportPlan(
            persons: $newPersons,
            matched: $matched,
            skippedPersons: $skippedPersons,
            relations: $relations,
            skippedRelations: $skippedRelations,
            marriages: $marriages,
            skippedMarriages: $skippedMarriages,
            skippedValues: $skippedValues,
            conflicts: $conflicts,
            births: $totals->births,
            deaths: $totals->deaths,
            weddings: $weddings,
            datesByQualifier: $totals->dated,
            undatedEvents: $totals->undated,
            noteValues: new NoteValueCounts($totals->places, $totals->burials, $totals->levels, $totals->eventNotes, $totals->variants),
            probandHint: $this->probandHint($reading, $skippedIds),
            snapshots: $snapshots,
            forgottenSnapshots: [],
            blockedReason: $tree->people()->whereNotNull('repo_id')->exists() ? self::BLOCKED_REASON : null,
        );
    }

    /**
     * The tree persons the decisions match, with their events, keyed by id. Validates the decisions.
     *
     * @return Collection<int, Person>
     *
     * @throws InvalidArgumentException
     */
    private function matchedPeople(Tree $tree, ResearchRepoReading $reading, IdentityDecisions $decisions): Collection
    {
        $decided = array_map(strval(...), [...array_keys($decisions->matched), ...$decisions->skipped, ...$decisions->new]);
        $expected = array_map(strval(...), array_keys($reading->persons));
        sort($decided);
        sort($expected);

        if ($decided !== $expected) {
            throw new InvalidArgumentException('Decyzje o tożsamości muszą obejmować każdą osobę repo dokładnie raz.');
        }

        $treeIds = array_values($decisions->matched);

        if (count(array_unique($treeIds)) !== count($treeIds)) {
            throw new InvalidArgumentException('Osoba drzewa może być dopasowana do najwyżej jednej osoby repo.');
        }

        $people = $tree->people()->whereIn('id', $treeIds)->with('events.participants')->get()->keyBy('id');

        if ($people->count() !== count($treeIds)) {
            throw new InvalidArgumentException('Decyzje wskazują osobę spoza drzewa konta.');
        }

        return $people;
    }

    /**
     * The graph of the tree's pairs, the gender of each node and a label for it.
     *
     * @param  array<string, ResearchPerson>  $persons
     * @param  array<string, string>  $nodes
     * @return array{0: SharedParentGraph, 1: array<string, Gender|null>, 2: array<string, string>}
     */
    private function sharedGraph(Tree $tree, array $persons, array $nodes, IdentityDecisions $decisions): array
    {
        $genders = [];
        $labels = [];

        foreach ($tree->people()->get(['id', 'first_name', 'last_name', 'gender']) as $person) {
            $node = SharedParentGraph::node($person->id);
            $genders[$node] = $person->gender;
            $labels[$node] = $person->displayName()." (#{$person->id})";
        }

        foreach ($nodes as $repoId => $node) {
            if (! isset($decisions->matched[$repoId])) {
                $genders[$node] = $persons[$repoId]->gender;
                $labels[$node] = $this->personLabel($persons[$repoId]);
            }
        }

        $graph = new SharedParentGraph;

        foreach ($tree->parentChildRelations()->orderBy('id')->get(['id', 'parent_id', 'child_id']) as $relation) {
            $graph->addExisting(SharedParentGraph::node($relation->parent_id), SharedParentGraph::node($relation->child_id), $relation->id);
        }

        return [$graph, $genders, $labels];
    }

    /**
     * Compare a repo person with the tree person it is matched to. A gender that would give a child two parents of one
     * sex is dropped; a gender conflict is left in `$pendingGenders` until the relations are planned.
     *
     * @param  array<string, Gender|null>  $genders
     * @param  array<string, string>  $labels
     * @param  list<ImportIssue>  $skippedValues
     * @param  array<string, array{0: string, 1: Gender, 2: Gender}>  $pendingGenders
     * @param  array<string, true>  $skippedGenders  repo ids whose gender the rules of the tree left out
     * @return array<string, PlannedFill|ValueConflict>
     */
    private function compareMatch(ResearchPerson $research, Person $person, string $node, SharedParentGraph $graph, array &$genders, array $labels, array &$skippedValues, array &$pendingGenders, array &$skippedGenders): array
    {
        $outcomes = [];
        $label = $this->personLabel($research);

        $outcomes['first_name'] = $this->compareText('first_name', 'Imiona', "{$research->id}/imiona", $label, $research->givenNames, $person->first_name);
        $outcomes['last_name'] = $this->compareText('last_name', 'Nazwisko', "{$research->id}/nazwisko", $label, $research->surname, $person->last_name);
        $outcomes['birth_date'] = $this->compareDate('birth_date', 'Data urodzenia', "{$research->id}/urodzenie/data", $label, $research->birth?->date, $person->birth());
        $outcomes['death_date'] = $this->compareDate('death_date', 'Data zgonu', "{$research->id}/zgon/data", $label, $research->death?->date, $person->death());

        $repoGender = $research->gender;

        if ($repoGender !== null) {
            $appGender = $person->gender;

            if ($appGender === null) {
                $offender = $this->sameSexOffender($graph, $genders, $node, $repoGender, true);

                if ($offender === null) {
                    $genders[$node] = $repoGender;
                    $outcomes['gender'] = new PlannedFill('gender', $repoGender, 'Płeć');
                } else {
                    $skippedValues[] = $this->genderSkipped($research, $repoGender, $offender, $labels);
                    $skippedGenders[$research->id] = true;
                }
            } elseif ($appGender !== $repoGender) {
                $pendingGenders[$research->id] = [$node, $repoGender, $appGender];
            }
        }

        return array_filter($outcomes, fn (PlannedFill|ValueConflict|null $outcome): bool => $outcome !== null);
    }

    private function compareText(string $field, string $label, string $key, string $person, ?string $repo, ?string $app): PlannedFill|ValueConflict|null
    {
        $repoText = self::cleanText($repo);

        if ($repoText === null) {
            return null;
        }

        $appText = self::cleanText($app);

        if ($appText === null) {
            return new PlannedFill($field, $repoText, $label);
        }

        if ($appText === $repoText) {
            return null;
        }

        return new ValueConflict($key, $person, $field, $label, $appText, $repoText, $repoText);
    }

    /**
     * A date the tree lacks is filled in, a refinement (month or day added to the same year) is a fill, anything else
     * that differs is a conflict. A missing event is not compared: the import creates it.
     */
    private function compareDate(string $field, string $label, string $key, string $person, ?ResearchDate $repo, ?Event $event): PlannedFill|ValueConflict|null
    {
        if ($repo === null || $event === null) {
            return null;
        }

        $app = ResearchDate::fromEvent($event);

        if ($app === null) {
            return new PlannedFill($field, $repo, $label);
        }

        if ($app == $repo) {
            return null;
        }

        if ($this->refines($repo, $app)) {
            return new PlannedFill($field, $repo, $label, $app->label());
        }

        return new ValueConflict($key, $person, $field, $label, $app->label(), $repo->label(), $repo);
    }

    /**
     * The repo date adds a month, or a day to the same month, to the same kind of date in the same year.
     */
    private function refines(ResearchDate $repo, ResearchDate $app): bool
    {
        if ($repo->qualifier !== $app->qualifier || $repo->year !== $app->year || $repo->untilYear !== $app->untilYear) {
            return false;
        }

        if ($app->month === null) {
            return $repo->month !== null;
        }

        return $repo->month === $app->month && $app->day === null && $repo->day !== null;
    }

    /**
     * A text with its spaces trimmed and collapsed, `null` when nothing is left. The apply step reads the tree through the same rule.
     */
    public static function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $clean === '' ? null : $clean;
    }

    /**
     * A child of the node who would have another parent of the same known gender if the node got that gender.
     *
     * @param  array<string, Gender|null>  $genders
     * @return array{child: string, parent: string}|null
     */
    private function sameSexOffender(SharedParentGraph $graph, array $genders, string $node, Gender $gender, bool $existingOnly): ?array
    {
        foreach ($graph->childrenOf($node, $existingOnly) as $child) {
            foreach ($graph->parentsOf($child, $existingOnly) as $other) {
                if ($other !== $node && ($genders[$other] ?? null) === $gender) {
                    return ['child' => $child, 'parent' => $other];
                }
            }
        }

        return null;
    }

    /**
     * @param  array{child: string, parent: string}  $offender
     * @param  array<string, string>  $labels
     */
    private function genderSkipped(ResearchPerson $research, Gender $gender, array $offender, array $labels): ImportIssue
    {
        return new ImportIssue($research->file, sprintf(
            'płeć z repo pominięta: %s (%s) dałaby dziecku %s dwoje rodziców tej samej płci (drugi rodzic: %s)',
            $gender->label(),
            $research->id,
            $labels[$offender['child']] ?? $offender['child'],
            $labels[$offender['parent']] ?? $offender['parent'],
        ));
    }

    /**
     * @param  array<string, ResearchPerson>  $persons  sorted by repo id
     * @param  array<string, string>  $nodes  repo id => graph node of every person that is not skipped
     * @param  array<string, Gender|null>  $genders
     * @param  array<string, string>  $labels
     * @param  Collection<int, Person>  $matchedPeople
     * @return array{0: list<PlannedRelation>, 1: list<ImportIssue>}
     */
    private function planRelations(array $persons, array $nodes, SharedParentGraph $graph, array $genders, array $labels, IdentityDecisions $decisions, Collection $matchedPeople): array
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

                $left = array_values(array_filter([$parent, $child], fn (ResearchPerson $person): bool => ! isset($nodes[$person->id])));

                if ($left !== []) {
                    $skipped[] = new ImportIssue($child->file, sprintf('relacja pominięta: %s → %s, osoba pominięta przy dopasowaniu (%s)', $parent->id, $child->id, implode(', ', array_map(fn (ResearchPerson $person): string => $person->id, $left))));

                    continue;
                }

                $candidates[] = [$parent, $child];
            }
        }

        $relations = [];

        foreach ($this->withoutRepoCycles($candidates, $persons, $skipped) as [$parent, $child]) {
            $p = $nodes[$parent->id];
            $c = $nodes[$child->id];
            $key = RepoKeys::relation($parent->id, $child->id);
            $existingId = $graph->existingId($p, $c);

            if ($existingId !== null) {
                $relations[] = new PlannedRelation($parent->id, $child->id, $key, $existingId);

                continue;
            }

            $reason = $this->relationConflict($graph, $genders, $labels, $p, $c);

            if ($reason === null && $graph->wouldCycle($p, $c)) {
                $reason = 'utworzyłaby cykl z relacjami w drzewie';
            }

            if ($reason !== null) {
                $skipped[] = new ImportIssue($child->file, sprintf(
                    'relacja pominięta: %s → %s: %s',
                    $this->described($parent->id, $decisions, $matchedPeople),
                    $this->described($child->id, $decisions, $matchedPeople),
                    $reason,
                ));

                continue;
            }

            $graph->addPlanned($p, $c);
            $relations[] = new PlannedRelation($parent->id, $child->id, $key);
        }

        return [$relations, $skipped];
    }

    /**
     * Why a new pair cannot be added to the graph without breaking a rule of the app; the cycle rule is checked apart.
     *
     * @param  array<string, Gender|null>  $genders
     * @param  array<string, string>  $labels
     */
    private function relationConflict(SharedParentGraph $graph, array $genders, array $labels, string $parent, string $child): ?string
    {
        $gender = $genders[$parent] ?? null;

        if ($gender !== null) {
            foreach ($graph->parentsOf($child, true) as $other) {
                if (($genders[$other] ?? null) === $gender) {
                    return sprintf('w drzewie %s: %s', $gender === Gender::Male ? 'inny ojciec' : 'inna matka', $labels[$other] ?? $other);
                }
            }
        }

        $current = $graph->parentsOf($child);

        if (count($current) >= 2) {
            return 'dziecko ma już dwoje rodziców: '.implode(', ', array_map(fn (string $node): string => $labels[$node] ?? $node, $current));
        }

        if ($gender !== null) {
            foreach ($current as $other) {
                if (($genders[$other] ?? null) === $gender) {
                    return sprintf('dziecko miałoby dwoje rodziców tej samej płci (%s)', $labels[$other] ?? $other);
                }
            }
        }

        return null;
    }

    /**
     * The repo id and, for a matched person, the person of the tree.
     *
     * @param  Collection<int, Person>  $matchedPeople
     */
    private function described(string $repoId, IdentityDecisions $decisions, Collection $matchedPeople): string
    {
        $person = isset($decisions->matched[$repoId]) ? $matchedPeople[$decisions->matched[$repoId]] : null;

        return $person === null ? $repoId : sprintf('%s (%s, #%d)', $repoId, $person->displayName(), $person->id);
    }

    /**
     * Drop every pair that lies on a parent-child cycle of the repo itself.
     *
     * @param  list<array{0: ResearchPerson, 1: ResearchPerson}>  $candidates
     * @param  array<string, ResearchPerson>  $persons
     * @param  list<ImportIssue>  $skipped
     * @return list<array{0: ResearchPerson, 1: ResearchPerson}>
     */
    private function withoutRepoCycles(array $candidates, array $persons, array &$skipped): array
    {
        $component = $this->componentsOf($candidates);
        $size = array_count_values($component);
        $members = [];

        foreach ($component as $id => $index) {
            $members[$index][] = $id;
        }

        $kept = [];

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

            $kept[] = [$parent, $child];
        }

        return $kept;
    }

    /**
     * @param  Collection<int, Person>  $matchedPeople
     * @param  array<string, true>  $skippedIds
     * @param  array<string, ResearchPerson>  $persons
     * @return array{0: list<PlannedMarriage>, 1: list<ImportIssue>, 2: list<ValueConflict>}
     */
    private function planMarriages(ResearchRepoReading $reading, IdentityDecisions $decisions, Collection $matchedPeople, array $skippedIds, array $persons): array
    {
        $marriages = [];
        $skipped = [];
        $conflicts = [];

        foreach ($reading->marriages as $marriage) {
            [$first, $second] = $marriage->personIds;
            $repoId = RepoKeys::marriage($first, $second);
            $left = array_values(array_filter($marriage->personIds, fn (string $id): bool => isset($skippedIds[$id])));

            if ($left !== []) {
                $skipped[] = new ImportIssue(implode(', ', $marriage->files), sprintf('małżeństwo %s pominięte: osoba pominięta przy dopasowaniu (%s)', $repoId, implode(', ', $left)));

                continue;
            }

            $existing = isset($decisions->matched[$first], $decisions->matched[$second])
                ? $this->weddingOf($matchedPeople[$decisions->matched[$first]], $decisions->matched[$second])
                : null;

            if ($existing === null) {
                $marriages[] = new PlannedMarriage($marriage, $repoId);

                continue;
            }

            $outcome = $this->compareDate(
                'marriage_date',
                'Data ślubu',
                "{$repoId}/data",
                $this->personLabel($persons[$first]).' i '.$this->personLabel($persons[$second]),
                $marriage->event->date,
                $existing,
            );

            if ($outcome instanceof ValueConflict) {
                $conflicts[] = $outcome;
            }

            $marriages[] = new PlannedMarriage(
                $marriage,
                $repoId,
                $existing->id,
                $outcome instanceof PlannedFill ? $outcome : null,
                $outcome instanceof ValueConflict ? $outcome : null,
            );
        }

        return [$marriages, $skipped, $conflicts];
    }

    /**
     * The lowest-id wedding of the person with the given spouse.
     */
    private function weddingOf(Person $person, int $spouseId): ?Event
    {
        return $person->events
            ->filter(fn (Event $event): bool => $event->type === EventType::Marriage && $event->participants->contains('id', $spouseId))
            ->sortBy('id')
            ->first();
    }

    /**
     * What the repo holds for the keys of a person: the person, its birth and its death, as the next import compares it.
     * A gender the rules of the tree left out has no entry, so that it comes back at the next import.
     *
     * @return array<string, array<string, string|null>>
     */
    private function snapshotsOf(ResearchPerson $research, ?string $note, bool $withGender): array
    {
        $snapshots = [RepoKeys::person($research->id) => RepoValues::person($research, $note, $withGender)];

        if ($research->birth !== null) {
            $snapshots[RepoKeys::birth($research->id)] = RepoValues::event($research->birth);
        }

        if ($research->death !== null) {
            $snapshots[RepoKeys::death($research->id)] = RepoValues::event($research->death);
        }

        return $snapshots;
    }

    private function personLabel(ResearchPerson $person): string
    {
        return sprintf('%s (%s)', RepoValues::nameOf($person), $person->id);
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

    /**
     * @param  array<string, true>  $skippedIds
     */
    private function probandHint(ResearchRepoReading $reading, array $skippedIds): ?ResearchPerson
    {
        $holders = array_values(array_filter(
            $reading->persons,
            fn (ResearchPerson $person): bool => in_array(1, $person->ahnentafel, true) && ! isset($skippedIds[$person->id]),
        ));

        return count($holders) === 1 ? $holders[0] : null;
    }
}
