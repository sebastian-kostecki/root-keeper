<?php

namespace App\Actions\Import;

use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\RepoKeyKind;
use App\Enums\ThreeWayOutcome;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\RepoSnapshot;
use App\Models\Tree;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ImportPlan;
use App\Support\Import\NoteBlock;
use App\Support\Import\NoteValueCounts;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedFill;
use App\Support\Import\PlannedMarriage;
use App\Support\Import\PlannedMatch;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedRelation;
use App\Support\Import\PlannedRemoval;
use App\Support\Import\PlannedRestoration;
use App\Support\Import\PlannedUpdate;
use App\Support\Import\RepoKeys;
use App\Support\Import\RepoRecord;
use App\Support\Import\RepoRecordClasses;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchMarriage;
use App\Support\Import\ResearchPerson;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\SharedParentGraph;
use App\Support\Import\ThreeWayValue;
use App\Support\Import\ValueConflict;
use InvalidArgumentException;

/**
 * @phpstan-type Item PlannedChange|PlannedFill|ValueConflict
 * @phpstan-type FieldPlan array{items: array<string, Item>, kept: int, skippedGender: bool}
 * @phpstan-type EventPlan array{items: array<string, Item>, kept: int, key: string|null, create: bool, values: array<string, string|null>|null, restoration: PlannedRestoration|null}
 * @phpstan-type Pending array{node: string, gender: Gender, outcome: ThreeWayOutcome, appLabel: string|null, lastLabel: string|null, hasLast: bool, label: string, person: string}
 */
class PlanImport
{
    /**
     * Until a later import phase lifts it, a tree that already holds a person from an import cannot take another import.
     */
    public const BLOCKED_REASON = 'Drzewo konta ma już osoby z importu. Ponowny import (S-04) jeszcze nie działa; nic nie zostanie zapisane.';

    /**
     * How an empty field is shown in a label.
     */
    public const EMPTY_LABEL = '—';

    private const SKIPPED_AT_MATCH = 'osoba pominięta przy dopasowaniu';

    private const SKIPPED_DELETED = 'osoba usunięta w aplikacji';

    private const SKIPPED_UNREAD = 'osoba pominięta przy odczycie repo';

    /**
     * The fields of a person in the order they are listed.
     */
    private const FIELDS = ['first_name', 'last_name', 'gender', 'note', 'birth_date', 'death_date'];

    /**
     * Decide what an import writes into the tree, without writing anything.
     *
     * The classification and the decisions come from the caller, who asked the author about them. A new repo person is
     * matched to a person of the tree, added or skipped as the identity decisions say; a person an earlier import
     * took keeps its values unless the repo changed a field nobody touched in the app (a change) or both moved (a
     * conflict). A record the repo dropped is deleted or detached, one deleted in the app is restored or left, as
     * decided. Parent-child pairs are checked on one graph made of the tree's pairs, minus the ones the plan removes,
     * and the planned ones, so that the write never meets a rule of the app.
     *
     * @throws InvalidArgumentException when the decisions do not cover each key the classification asked about once or point outside the tree
     */
    public function handle(Tree $tree, ResearchRepoReading $reading, RepoRecordClasses $classes, ImportDecisions $decisions): ImportPlan
    {
        $identity = $decisions->identity;

        $this->checkDecisions($classes, $decisions);

        $matchedPeople = $this->matchedPeople($tree, $identity);
        $knownPeople = $this->knownPeople($tree, $classes);
        $treePeople = $knownPeople + $matchedPeople;
        /** @var array<string, RepoSnapshot> $memory */
        $memory = $tree->repoSnapshots()->get()->keyBy('repo_id')->all();
        $knownRelationKeys = array_fill_keys(array_map(strval(...), $tree->parentChildRelations()->whereNotNull('repo_id')->pluck('repo_id')->all()), true);
        $knownWeddings = $tree->events()->where('type', EventType::Marriage)->whereNotNull('repo_id')->get()->keyBy('repo_id')->all();

        [$restoredPeople, $keptPeople, $notPlanned, $settled, $restoredEntries, $deletedEntries] = $this->deletionIndex($classes, $decisions);

        $persons = $reading->persons;
        ksort($persons);

        $noteSkipped = array_fill_keys($identity->skipped, true);
        $notes = [];
        $skippedIds = [];
        $nodes = [];

        foreach ($persons as $repoId => $research) {
            $notes[$repoId] = RepoValues::note($research, $reading, $noteSkipped);

            if (isset($knownPeople[$repoId])) {
                $nodes[$repoId] = SharedParentGraph::node($knownPeople[$repoId]->id);
            } elseif ($identity->isSkipped($repoId)) {
                $skippedIds[$repoId] = self::SKIPPED_AT_MATCH;
            } elseif (isset($matchedPeople[$repoId])) {
                $nodes[$repoId] = SharedParentGraph::node($matchedPeople[$repoId]->id);
            } elseif (in_array($repoId, $identity->new, true) || isset($restoredPeople[$repoId])) {
                $nodes[$repoId] = $repoId;
            } elseif (isset($keptPeople[$repoId])) {
                $skippedIds[$repoId] = self::SKIPPED_DELETED;
            } else {
                $skippedIds[$repoId] = self::SKIPPED_UNREAD;
            }
        }

        [$gonePeople, $goneRelations] = $this->goneRecords($classes, $decisions);
        [$graph, $genders, $labels] = $this->sharedGraph($tree, $persons, $nodes, $gonePeople, $goneRelations);

        $skippedValues = [];
        $pendingGenders = [];
        /** @var array<string, array<string, Item>> $items */
        $items = [];
        /** @var array<string, int> $keptByPerson */
        $keptByPerson = [];
        /** @var array<string, true> $skippedGenders */
        $skippedGenders = [];
        /** @var array<string, array{birth: EventPlan, death: EventPlan}> $eventPlans */
        $eventPlans = [];

        foreach ($persons as $repoId => $research) {
            if (! isset($treePeople[$repoId])) {
                continue;
            }

            $person = $treePeople[$repoId];
            $isKnown = isset($knownPeople[$repoId]);
            $snapshot = $isKnown ? ($memory[$repoId] ?? null) : null;
            $fieldPlan = $this->planFields($research, $person, $nodes[$repoId], $snapshot, $isKnown, $notes[$repoId], $graph, $genders, $labels, $skippedValues, $pendingGenders);
            $birthPlan = $this->planEvent(true, $research, $person, $isKnown ? $memory : [], $decisions, $settled);
            $deathPlan = $this->planEvent(false, $research, $person, $isKnown ? $memory : [], $decisions, $settled);

            $items[$repoId] = [...$fieldPlan['items'], ...$birthPlan['items'], ...$deathPlan['items']];
            $keptByPerson[$repoId] = $fieldPlan['kept'] + $birthPlan['kept'] + $deathPlan['kept'];

            if ($fieldPlan['skippedGender']) {
                $skippedGenders[$repoId] = true;
            }

            $eventPlans[$repoId] = ['birth' => $birthPlan, 'death' => $deathPlan];
        }

        [$relations, $skippedRelations, $knownRelations, $relationRestorations] = $this->planRelations($persons, $nodes, $graph, $genders, $labels, $skippedIds, $treePeople, $notPlanned, $knownRelationKeys, $restoredEntries);

        foreach ($pendingGenders as $repoId => $pending) {
            $offender = $this->sameSexOffender($graph, $genders, $pending['node'], $pending['gender'], false);

            if ($offender !== null) {
                $skippedValues[] = $this->genderSkipped($persons[$repoId], $pending['gender'], $offender, $labels);
                $skippedGenders[$repoId] = true;

                continue;
            }

            $genders[$pending['node']] = $pending['gender'];
            $item = $this->itemFor(
                $pending['outcome'],
                'gender',
                'Płeć',
                "{$repoId}/plec",
                $pending['person'],
                $pending['appLabel'],
                $pending['gender']->label(),
                $pending['lastLabel'],
                $pending['hasLast'],
                $pending['gender'],
            );

            if ($item !== null) {
                $items[$repoId]['gender'] = $item;
            }
        }

        $newPersons = [];
        $matched = [];
        $updates = [];
        $changes = [];
        $skippedPersons = [];
        $conflicts = [];
        $restorations = [];
        $snapshots = [];
        $keptManual = 0;
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
                if ($skippedIds[$repoId] !== self::SKIPPED_DELETED) {
                    $skippedPersons[] = new ImportIssue($research->file, $skippedIds[$repoId]);
                }

                continue;
            }

            $note = $notes[$repoId];

            if (! isset($treePeople[$repoId])) {
                $planned = new PlannedPerson(
                    $research,
                    $note,
                    $research->birth === null ? null : RepoKeys::birth($research->id),
                    $research->death === null ? null : RepoKeys::death($research->id),
                );
                $snapshots += $this->snapshotsOf($research, $note);
                $totals->variants += count($research->surnameVariants);
                $totals->burials += $research->burial === null ? 0 : 1;

                foreach ([$research->birth, $research->death, $research->burial] as $event) {
                    $this->tallyNoteValues($event, $totals->places, $totals->levels, $totals->eventNotes);
                }

                foreach ([$research->birth, $research->death] as $event) {
                    $this->tally($event, $totals->dated, $totals->undated);
                }

                $totals->births += $research->birth === null ? 0 : 1;
                $totals->deaths += $research->death === null ? 0 : 1;

                if (isset($restoredPeople[$repoId])) {
                    $restorations[] = new PlannedRestoration(
                        $repoId,
                        RepoKeyKind::Person,
                        $this->personLabel($research),
                        array_map(fn (RepoRecord $member): string => $member->key, $deletedEntries[$repoId]->members),
                        person: $planned,
                    );
                } else {
                    $newPersons[] = $planned;
                }

                continue;
            }

            $person = $treePeople[$repoId];
            $isKnown = isset($knownPeople[$repoId]);
            $eventPlan = $eventPlans[$repoId];

            $snapshots[RepoKeys::person($repoId)] = $this->personValues($research, $note, isset($skippedGenders[$repoId]), $isKnown ? ($memory[$repoId] ?? null) : null);

            foreach (['birth' => [$research->birth, RepoKeys::birth($repoId)], 'death' => [$research->death, RepoKeys::death($repoId)]] as $kind => [$event, $key]) {
                $plan = $eventPlan[$kind];

                if ($plan['values'] !== null) {
                    $snapshots[$key] = $plan['values'];
                }

                if ($plan['restoration'] !== null) {
                    $restorations[] = $plan['restoration'];
                }

                if ($plan['create'] || $plan['restoration'] !== null) {
                    if ($kind === 'birth') {
                        $totals->births++;
                    } else {
                        $totals->deaths++;
                    }

                    $this->tally($event, $totals->dated, $totals->undated);
                }
            }

            $personChanges = [];
            $fills = [];
            $personConflicts = [];

            foreach (self::FIELDS as $field) {
                $item = $items[$repoId][$field] ?? null;

                if ($item instanceof PlannedChange) {
                    $personChanges[] = $item;
                } elseif ($item instanceof PlannedFill) {
                    $fills[] = $item;
                } elseif ($item instanceof ValueConflict) {
                    $personConflicts[] = $item;
                }
            }

            array_push($conflicts, ...$personConflicts);

            if (! $isKnown) {
                $matched[] = new PlannedMatch(
                    $research,
                    $person->id,
                    $note,
                    $eventPlan['birth']['key'],
                    $eventPlan['death']['key'],
                    $eventPlan['birth']['create'],
                    $eventPlan['death']['create'],
                    $fills,
                    $personConflicts,
                );
                $totals->variants += count($research->surnameVariants);
                $totals->burials += $research->burial === null ? 0 : 1;

                foreach ([$research->birth, $research->death, $research->burial] as $event) {
                    $this->tallyNoteValues($event, $totals->places, $totals->levels, $totals->eventNotes);
                }

                continue;
            }

            $keptManual += $keptByPerson[$repoId];
            array_push($changes, ...$personChanges);
            $hasWork = $personChanges !== [] || $fills !== [] || $personConflicts !== []
                || $eventPlan['birth']['key'] !== null || $eventPlan['death']['key'] !== null;

            if ($hasWork) {
                $updates[] = new PlannedUpdate(
                    $research,
                    $person->id,
                    $personChanges,
                    $fills,
                    $personConflicts,
                    $keptByPerson[$repoId],
                    $eventPlan['birth']['key'],
                    $eventPlan['death']['key'],
                    $eventPlan['birth']['create'],
                    $eventPlan['death']['create'],
                );
            }
        }

        $marriagePlan = $this->planMarriages($reading, $treePeople, $skippedIds, $persons, $knownWeddings, $memory, $notPlanned, $restoredEntries);
        $marriages = $marriagePlan['planned'];
        $skippedMarriages = $marriagePlan['skipped'];
        array_push($conflicts, ...$marriagePlan['conflicts']);
        array_push($changes, ...$marriagePlan['changes']);
        array_push($restorations, ...$relationRestorations, ...$marriagePlan['restorations']);
        $keptManual += $marriagePlan['kept'];

        foreach ($knownRelations as $key) {
            $snapshots[$key] = [];
        }

        foreach ($relations as $planned) {
            $snapshots[$planned->repoId] = [];
        }

        $weddings = 0;

        foreach ($marriages as $planned) {
            $snapshots[$planned->repoId] = ['data' => $planned->marriage->event->date?->label()];

            if (! $planned->known) {
                $this->tallyNoteValues($planned->marriage->event, $totals->places, $totals->levels, $totals->eventNotes);
            }

            if ($planned->existingEventId === null) {
                $weddings++;
                $this->tally($planned->marriage->event, $totals->dated, $totals->undated);
            }
        }

        foreach ($marriagePlan['knownKeys'] as $key => $values) {
            $snapshots[$key] = $values;
        }

        [$removals, $detachedBirths, $forgotten] = $this->planRemovals($classes, $decisions);
        $keptDeleted = $this->keptDeletedSnapshots($classes, $decisions, $reading);
        $unassessable = array_map(
            fn (RepoRecord $entry): ImportIssue => new ImportIssue($entry->key, 'nie do oceny: popraw pominięte wpisy repo'),
            $classes->unassessable,
        );

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
            probandHint: $this->probandHint($reading, array_fill_keys(array_keys($skippedIds), true)),
            snapshots: $snapshots,
            forgottenSnapshots: array_values(array_unique([...$forgotten, ...$classes->goneBoth])),
            updates: $updates,
            changes: $changes,
            removals: $removals,
            restorations: $restorations,
            detachedBirths: $detachedBirths,
            unassessable: $unassessable,
            keptManual: $keptManual,
            keptDeletedSnapshots: $keptDeleted,
            seenSnapshots: $this->seenSnapshots($memory, [...array_keys($snapshots), ...array_keys($keptDeleted), ...$forgotten, ...$classes->goneBoth]),
        );
    }

    /**
     * The fingerprint of each memory entry among the keys the write touches, so that the write can tell whether another
     * import changed the memory after planning.
     *
     * @param  array<string, RepoSnapshot>  $memory
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function seenSnapshots(array $memory, array $keys): array
    {
        $seen = [];

        foreach (array_unique($keys) as $key) {
            if (isset($memory[$key])) {
                $seen[$key] = $memory[$key]->fingerprint();
            }
        }

        return $seen;
    }

    /**
     * Check that the decisions answer each question the classification raised exactly once, with a legal answer.
     *
     * @throws InvalidArgumentException
     */
    private function checkDecisions(RepoRecordClasses $classes, ImportDecisions $decisions): void
    {
        $identity = $decisions->identity;
        $decided = [...array_map(strval(...), array_keys($identity->matched)), ...$identity->skipped, ...$identity->new];
        $expected = $classes->newPersonIds;
        sort($decided);
        sort($expected);

        if ($decided !== $expected) {
            throw new InvalidArgumentException('Decyzje o tożsamości muszą obejmować każdą nową osobę repo dokładnie raz.');
        }

        $this->checkAnswers(
            $classes->removedInRepo,
            $decisions->removals,
            [ImportDecisions::DELETE, ImportDecisions::DETACH],
            'Decyzje o rekordach usuniętych w repo muszą obejmować każdy taki rekord dokładnie raz.',
        );
        $this->checkAnswers(
            $classes->deletedInApp,
            $decisions->deletions,
            [ImportDecisions::RESTORE, ImportDecisions::KEEP],
            'Decyzje o rekordach usuniętych w aplikacji muszą obejmować każdy taki rekord dokładnie raz.',
        );

        foreach ($classes->removedInRepo as $entry) {
            if ($entry->detachOnly() && $decisions->removals[$entry->key] !== ImportDecisions::DETACH) {
                throw new InvalidArgumentException("Urodzenie {$entry->key} można tylko odłączyć od repo.");
            }
        }
    }

    /**
     * @param  list<RepoRecord>  $entries
     * @param  array<string, string>  $answers
     * @param  list<string>  $allowed
     *
     * @throws InvalidArgumentException
     */
    private function checkAnswers(array $entries, array $answers, array $allowed, string $message): void
    {
        $asked = array_map(fn (RepoRecord $entry): string => $entry->key, $entries);
        $answered = array_map(strval(...), array_keys($answers));
        sort($asked);
        sort($answered);

        if ($asked !== $answered) {
            throw new InvalidArgumentException($message);
        }

        foreach ($answers as $key => $answer) {
            if (! in_array($answer, $allowed, true)) {
                throw new InvalidArgumentException("Niewłaściwa decyzja o rekordzie {$key}: {$answer}.");
            }
        }
    }

    /**
     * The tree persons the identity decisions match, with their events, by repo id. Validates the decisions.
     *
     * @return array<string, Person>
     *
     * @throws InvalidArgumentException
     */
    private function matchedPeople(Tree $tree, IdentityDecisions $identity): array
    {
        $treeIds = array_values($identity->matched);

        if (count(array_unique($treeIds)) !== count($treeIds)) {
            throw new InvalidArgumentException('Osoba drzewa może być dopasowana do najwyżej jednej osoby repo.');
        }

        $people = $tree->people()->whereIn('id', $treeIds)->with('events.participants')->get()->keyBy('id');

        if ($people->count() !== count($treeIds)) {
            throw new InvalidArgumentException('Decyzje wskazują osobę spoza drzewa konta.');
        }

        $matched = [];

        foreach ($identity->matched as $repoId => $treeId) {
            $person = $people[$treeId];

            if ($person->repo_id !== null) {
                throw new InvalidArgumentException("Osoba drzewa #{$treeId} ma już id z importu, więc nie może być dopasowana do osoby z repo.");
            }

            $matched[(string) $repoId] = $person;
        }

        return $matched;
    }

    /**
     * The persons of the tree that an earlier import took, with their events, by repo id.
     *
     * @return array<string, Person>
     *
     * @throws InvalidArgumentException
     */
    private function knownPeople(Tree $tree, RepoRecordClasses $classes): array
    {
        $ids = array_values(array_map(fn (Person $person): int => $person->id, $classes->knownPersons));
        $people = $tree->people()->whereIn('id', $ids)->with('events.participants')->get()->keyBy('id');

        if ($people->count() !== count($ids)) {
            throw new InvalidArgumentException('Klasyfikacja wskazuje osobę spoza drzewa konta.');
        }

        $known = [];

        foreach ($classes->knownPersons as $repoId => $classified) {
            $person = $people[$classified->id];

            if ($person->repo_id !== $repoId) {
                throw new InvalidArgumentException("Klasyfikacja nie pasuje do drzewa konta (osoba {$repoId}).");
            }

            $known[$repoId] = $person;
        }

        return $known;
    }

    /**
     * What the decisions say about the records deleted in the app.
     *
     * @return array{0: array<string, true>, 1: array<string, true>, 2: array<string, true>, 3: array<string, true>, 4: array<string, RepoRecord>, 5: array<string, RepoRecord>}
     *                                                                                                                                                                           the persons to restore, the persons left deleted, the keys the plan must not create, the keys already settled, the restored entries that are not persons, every asked entry
     */
    private function deletionIndex(RepoRecordClasses $classes, ImportDecisions $decisions): array
    {
        $restoredPeople = [];
        $keptPeople = [];
        $notPlanned = [];
        $settled = [];
        $restoredEntries = [];
        $entries = [];

        foreach ($classes->deletedInApp as $entry) {
            $entries[$entry->key] = $entry;
            $restore = $decisions->deletions[$entry->key] === ImportDecisions::RESTORE;

            if ($entry->kind === RepoKeyKind::Person) {
                if ($restore) {
                    $restoredPeople[$entry->key] = true;
                } else {
                    $keptPeople[$entry->key] = true;
                }
            } elseif ($restore) {
                $restoredEntries[$entry->key] = $entry;
            }

            if (! $restore) {
                foreach ([$entry, ...$entry->members] as $each) {
                    $notPlanned[$each->key] = true;
                }
            }
        }

        foreach ($classes->keptDeleted as $key) {
            $notPlanned[$key] = true;
            $settled[$key] = true;

            if (RepoKeys::kindOf($key) === RepoKeyKind::Person) {
                $keptPeople[$key] = true;
            }
        }

        return [$restoredPeople, $keptPeople, $notPlanned, $settled, $restoredEntries, $entries];
    }

    /**
     * The persons and the parent-child pairs the plan deletes, by id: a deleted person takes every pair with it.
     *
     * @return array{0: array<int, true>, 1: array<int, true>}
     */
    private function goneRecords(RepoRecordClasses $classes, ImportDecisions $decisions): array
    {
        $people = [];
        $relations = [];

        foreach ($classes->removedInRepo as $entry) {
            if ($decisions->removals[$entry->key] !== ImportDecisions::DELETE) {
                continue;
            }

            if ($entry->record instanceof Person) {
                $people[$entry->record->id] = true;
            } elseif ($entry->record instanceof ParentChildRelation) {
                $relations[$entry->record->id] = true;
            }
        }

        return [$people, $relations];
    }

    /**
     * The graph of the tree's pairs without the ones the plan deletes, the gender of each node and a label for it.
     *
     * @param  array<string, ResearchPerson>  $persons
     * @param  array<string, string>  $nodes
     * @param  array<int, true>  $gonePeople
     * @param  array<int, true>  $goneRelations
     * @return array{0: SharedParentGraph, 1: array<string, Gender|null>, 2: array<string, string>}
     */
    private function sharedGraph(Tree $tree, array $persons, array $nodes, array $gonePeople, array $goneRelations): array
    {
        $genders = [];
        $labels = [];

        foreach ($tree->people()->get(['id', 'first_name', 'last_name', 'gender']) as $person) {
            if (isset($gonePeople[$person->id])) {
                continue;
            }

            $node = SharedParentGraph::node($person->id);
            $genders[$node] = $person->gender;
            $labels[$node] = $person->displayName()." (#{$person->id})";
        }

        foreach ($nodes as $repoId => $node) {
            if ($node === $repoId) {
                $genders[$node] = $persons[$repoId]->gender;
                $labels[$node] = $this->personLabel($persons[$repoId]);
            }
        }

        $graph = new SharedParentGraph;

        foreach ($tree->parentChildRelations()->orderBy('id')->get(['id', 'parent_id', 'child_id']) as $relation) {
            if (isset($goneRelations[$relation->id]) || isset($gonePeople[$relation->parent_id]) || isset($gonePeople[$relation->child_id])) {
                continue;
            }

            $graph->addExisting(SharedParentGraph::node($relation->parent_id), SharedParentGraph::node($relation->child_id), $relation->id);
        }

        return [$graph, $genders, $labels];
    }

    /**
     * Compare the fields of a person of the tree with the repo: the names, the gender and, for a person an earlier import
     * took, the note block. A person without a memory of the last import is compared the way a hand-entered one is: an
     * empty field is filled and any other difference is a conflict. A gender that would give a child two parents of
     * one sex is dropped; a gender change or conflict is left in `$pendingGenders` until the relations are planned.
     *
     * @param  bool  $withNote  compare the note block; a matched person gets the block appended instead
     * @param  array<string, Gender|null>  $genders
     * @param  array<string, string>  $labels
     * @param  list<ImportIssue>  $skippedValues
     * @param  array<string, Pending>  $pendingGenders
     * @return FieldPlan
     */
    private function planFields(ResearchPerson $research, Person $person, string $node, ?RepoSnapshot $snapshot, bool $withNote, ?string $note, SharedParentGraph $graph, array &$genders, array $labels, array &$skippedValues, array &$pendingGenders): array
    {
        $items = [];
        $kept = 0;
        $skippedGender = false;
        $who = $this->personLabel($research);

        $texts = [
            ['first_name', 'Imiona', 'imiona', self::cleanText($research->givenNames), self::cleanText($person->first_name)],
            ['last_name', 'Nazwisko', 'nazwisko', self::cleanText($research->surname), self::cleanText($person->last_name)],
        ];

        if ($withNote) {
            $texts[] = ['note', 'Notatka', 'notatka', $note, NoteBlock::find($person->note, $research->id)];
        }

        foreach ($texts as [$field, $label, $name, $repo, $app]) {
            [$hasLast, $last] = $this->lastOf($snapshot, $name);
            $outcome = ThreeWayValue::compare($repo, $hasLast, $last, $app);
            $kept += $outcome === ThreeWayOutcome::KeptManual ? 1 : 0;
            $item = $this->itemFor($outcome, $field, $label, "{$research->id}/{$name}", $who, $app, $repo, $last, $hasLast, $repo);

            if ($item !== null) {
                $items[$field] = $item;
            }
        }

        $repoGender = $research->gender;
        $appGender = $person->gender;
        [$hasLast, $last] = $this->lastOf($snapshot, 'plec');
        $outcome = ThreeWayValue::compare($repoGender?->value, $hasLast, $last, $appGender?->value);
        $lastLabel = $last === null ? null : (Gender::tryFrom($last)?->label() ?? $last);

        if ($outcome === ThreeWayOutcome::KeptManual) {
            $kept++;
        } elseif ($outcome === ThreeWayOutcome::Fill && $repoGender !== null) {
            $offender = $this->sameSexOffender($graph, $genders, $node, $repoGender, true);

            if ($offender === null) {
                $genders[$node] = $repoGender;
                $items['gender'] = new PlannedFill('gender', $repoGender, 'Płeć');
            } else {
                $skippedValues[] = $this->genderSkipped($research, $repoGender, $offender, $labels);
                $skippedGender = true;
            }
        } elseif ($outcome === ThreeWayOutcome::Changed || $outcome === ThreeWayOutcome::Conflict) {
            if ($repoGender === null) {
                $item = $this->itemFor($outcome, 'gender', 'Płeć', "{$research->id}/plec", $who, $appGender?->label(), null, $lastLabel, $hasLast, null);

                if ($item !== null) {
                    $items['gender'] = $item;
                }
            } else {
                $pendingGenders[$research->id] = [
                    'node' => $node,
                    'gender' => $repoGender,
                    'outcome' => $outcome,
                    'appLabel' => $appGender?->label(),
                    'lastLabel' => $lastLabel,
                    'hasLast' => $hasLast,
                    'label' => 'Płeć',
                    'person' => $who,
                ];
            }
        }

        return ['items' => $items, 'kept' => $kept, 'skippedGender' => $skippedGender];
    }

    /**
     * Whether the memory of the last import holds a value for the field, and the value.
     *
     * @return array{0: bool, 1: string|null}
     */
    private function lastOf(?RepoSnapshot $snapshot, string $field): array
    {
        if ($snapshot === null || ! array_key_exists($field, $snapshot->values)) {
            return [false, null];
        }

        return [true, $snapshot->values[$field]];
    }

    /**
     * What the outcome of the three values asks for: a change the author may reject, a fill, a conflict, or nothing.
     *
     * @param  string|null  $fillAppLabel  what the app holds when a fill refines a value (a date), `null` for an empty field
     * @return Item|null
     */
    private function itemFor(ThreeWayOutcome $outcome, string $field, string $label, string $key, string $person, ?string $appLabel, ?string $repoLabel, ?string $lastLabel, bool $hasLast, string|Gender|ResearchDate|null $repoValue, ?string $fillAppLabel = null): PlannedChange|PlannedFill|ValueConflict|null
    {
        return match ($outcome) {
            ThreeWayOutcome::Same, ThreeWayOutcome::KeptManual => null,
            ThreeWayOutcome::Changed => new PlannedChange($key, $person, $field, $label, $appLabel ?? self::EMPTY_LABEL, $repoLabel ?? self::EMPTY_LABEL, $repoValue),
            ThreeWayOutcome::Conflict => new ValueConflict($key, $person, $field, $label, $appLabel ?? self::EMPTY_LABEL, $repoLabel ?? self::EMPTY_LABEL, $repoValue, $hasLast ? ($lastLabel ?? self::EMPTY_LABEL) : null),
            ThreeWayOutcome::Fill => $repoValue === null ? null : new PlannedFill($field, $repoValue, $label, $fillAppLabel),
        };
    }

    /**
     * The three values of a date: the repo date, the one at the last import and the one of the event.
     *
     * @return array{0: Item|null, 1: bool} the item to plan and whether a change of the app value was left alone
     */
    private function dateItem(string $field, string $label, string $key, string $who, ?ResearchDate $repo, ?Event $event, ?RepoSnapshot $snapshot): array
    {
        $app = ResearchDate::fromEvent($event);
        [$hasLast, $last] = $this->lastOf($snapshot, 'data');
        $same = $repo === null ? $app === null : ($app !== null && $app == $repo);
        $outcome = ThreeWayValue::compare(
            $repo?->label(),
            $hasLast,
            $last,
            $app?->label(),
            $repo !== null && $app !== null && $this->refines($repo, $app),
            $same,
        );

        return [
            $this->itemFor($outcome, $field, $label, $key, $who, $app?->label(), $repo?->label(), $last, $hasLast, $repo, $app?->label()),
            $outcome === ThreeWayOutcome::KeptManual,
        ];
    }

    /**
     * Plan the birth or the death of a person of the tree. The event an earlier import took is compared with the three
     * values. Otherwise the repo key goes on the event the person has without one, compared like a hand-entered value,
     * or the event is created, or it is restored when the author deleted it in the app and chose to bring it back.
     *
     * @param  array<string, RepoSnapshot>  $memory  empty for a person that is not known yet
     * @param  array<string, true>  $settled  keys the author already left deleted
     * @return EventPlan
     */
    private function planEvent(bool $birth, ResearchPerson $research, Person $person, array $memory, ImportDecisions $decisions, array $settled): array
    {
        $none = ['items' => [], 'kept' => 0, 'key' => null, 'create' => false, 'values' => null, 'restoration' => null];
        $repoEvent = $birth ? $research->birth : $research->death;

        if ($repoEvent === null) {
            return $none;
        }

        $type = $birth ? EventType::Birth : EventType::Death;
        $key = $birth ? RepoKeys::birth($research->id) : RepoKeys::death($research->id);
        $field = $birth ? 'birth_date' : 'death_date';
        $label = $birth ? 'Data urodzenia' : 'Data zgonu';
        $who = $this->personLabel($research);
        $values = RepoValues::event($repoEvent);
        $decision = $decisions->deletions[$key] ?? null;
        $own = $person->events->first(fn (Event $event): bool => $event->type === $type && $event->repo_id === $key);

        if ($own !== null) {
            [$item, $kept] = $this->dateItem($field, $label, "{$key}/data", $who, $repoEvent->date, $own, $memory[$key] ?? null);

            return [...$none, 'items' => $item === null ? [] : [$field => $item], 'kept' => $kept ? 1 : 0, 'values' => $values];
        }

        if ($decision === ImportDecisions::KEEP || isset($settled[$key])) {
            return $none;
        }

        $unmarked = $person->events->first(fn (Event $event): bool => $event->type === $type && $event->repo_id === null);

        if ($unmarked !== null) {
            [$item] = $this->dateItem($field, $label, "{$key}/data", $who, $repoEvent->date, $unmarked, null);

            return [...$none, 'items' => $item === null ? [] : [$field => $item], 'key' => $key, 'values' => $values];
        }

        if ($decision === ImportDecisions::RESTORE) {
            return [...$none, 'values' => $values, 'restoration' => new PlannedRestoration(
                $key,
                $birth ? RepoKeyKind::Birth : RepoKeyKind::Death,
                ($birth ? 'Urodzenie' : 'Zgon').': '.$who,
                personId: $person->id,
                event: $repoEvent,
            )];
        }

        return [...$none, 'key' => $key, 'create' => true, 'values' => $values];
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
     * @param  array<string, string>  $skippedIds  repo id of a person left out => why
     * @param  array<string, Person>  $treePeople  repo id => the tree person of every matched and known person
     * @param  array<string, true>  $notPlanned  keys of records left deleted in the app
     * @param  array<string, true>  $knownRelationKeys  keys of the pairs of the tree that carry a repo id
     * @param  array<string, RepoRecord>  $restoredEntries
     * @return array{0: list<PlannedRelation>, 1: list<ImportIssue>, 2: list<string>, 3: list<PlannedRestoration>} the pairs, why pairs were skipped, the known pairs that stay as they are, the restored pairs
     */
    private function planRelations(array $persons, array $nodes, SharedParentGraph $graph, array $genders, array $labels, array $skippedIds, array $treePeople, array $notPlanned, array $knownRelationKeys, array $restoredEntries): array
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
                    $skipped[] = new ImportIssue($child->file, sprintf('relacja pominięta: %s → %s, %s', $parent->id, $child->id, $this->leftDescription(array_map(fn (ResearchPerson $person): string => $person->id, $left), $skippedIds)));

                    continue;
                }

                if (isset($notPlanned[RepoKeys::relation($parent->id, $child->id)])) {
                    continue;
                }

                $candidates[] = [$parent, $child];
            }
        }

        $relations = [];
        $known = [];
        $restorations = [];

        foreach ($this->withoutRepoCycles($candidates, $persons, $skipped) as [$parent, $child]) {
            $p = $nodes[$parent->id];
            $c = $nodes[$child->id];
            $key = RepoKeys::relation($parent->id, $child->id);

            if (isset($knownRelationKeys[$key])) {
                $known[] = $key;

                continue;
            }

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
                    $this->described($parent->id, $treePeople),
                    $this->described($child->id, $treePeople),
                    $reason,
                ));

                continue;
            }

            $graph->addPlanned($p, $c);
            $planned = new PlannedRelation($parent->id, $child->id, $key);
            $relations[] = $planned;

            if (isset($restoredEntries[$key])) {
                $restorations[] = new PlannedRestoration(
                    $key,
                    RepoKeyKind::Relation,
                    sprintf('Relacja: %s → %s', $this->personLabel($parent), $this->personLabel($child)),
                    relation: $planned,
                );
            }
        }

        return [$relations, $skipped, $known, $restorations];
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
     * The repo id and, for a person that is in the tree, the person of the tree.
     *
     * @param  array<string, Person>  $treePeople
     */
    private function described(string $repoId, array $treePeople): string
    {
        $person = $treePeople[$repoId] ?? null;

        return $person === null ? $repoId : sprintf('%s (%s, #%d)', $repoId, $person->displayName(), $person->id);
    }

    /**
     * Why persons are left out of the import, e.g. "osoba pominięta przy dopasowaniu (O-0002)".
     *
     * @param  list<string>  $ids
     * @param  array<string, string>  $skippedIds  repo id => why
     */
    private function leftDescription(array $ids, array $skippedIds): string
    {
        $byReason = [];

        foreach ($ids as $id) {
            $byReason[$skippedIds[$id] ?? self::SKIPPED_AT_MATCH][] = $id;
        }

        $parts = [];

        foreach ($byReason as $reason => $reasonIds) {
            $parts[] = sprintf('%s (%s)', $reason, implode(', ', $reasonIds));
        }

        return implode('; ', $parts);
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
     * @param  array<string, Person>  $treePeople  repo id => the tree person of every matched and known person
     * @param  array<string, string>  $skippedIds  repo id of a person left out => why
     * @param  array<string, ResearchPerson>  $persons
     * @param  array<string, Event>  $knownWeddings  weddings of the tree that carry a repo id, by key
     * @param  array<string, RepoSnapshot>  $memory
     * @param  array<string, true>  $notPlanned
     * @param  array<string, RepoRecord>  $restoredEntries
     * @return array{planned: list<PlannedMarriage>, skipped: list<ImportIssue>, conflicts: list<ValueConflict>, changes: list<PlannedChange>, restorations: list<PlannedRestoration>, kept: int, knownKeys: array<string, array<string, string|null>>}
     */
    private function planMarriages(ResearchRepoReading $reading, array $treePeople, array $skippedIds, array $persons, array $knownWeddings, array $memory, array $notPlanned, array $restoredEntries): array
    {
        $planned = [];
        $skipped = [];
        $conflicts = [];
        $changes = [];
        $restorations = [];
        $knownKeys = [];
        $kept = 0;

        foreach ($reading->marriages as $marriage) {
            [$first, $second] = $marriage->personIds;
            $repoId = RepoKeys::marriage($first, $second);
            $left = array_values(array_filter($marriage->personIds, fn (string $id): bool => isset($skippedIds[$id])));

            if ($left !== []) {
                $skipped[] = new ImportIssue(implode(', ', $marriage->files), sprintf('małżeństwo %s pominięte: %s', $repoId, $this->leftDescription($left, $skippedIds)));

                continue;
            }

            if (isset($notPlanned[$repoId])) {
                continue;
            }

            $who = $this->personLabel($persons[$first]).' i '.$this->personLabel($persons[$second]);
            $own = $knownWeddings[$repoId] ?? null;

            if ($own !== null) {
                [$item, $keptHere] = $this->dateItem('marriage_date', 'Data ślubu', "{$repoId}/data", $who, $marriage->event->date, $own, $memory[$repoId] ?? null);
                $kept += $keptHere ? 1 : 0;
                $knownKeys[$repoId] = RepoValues::event($marriage->event);

                if ($item !== null) {
                    $planned[] = $this->plannedWedding($marriage, $repoId, $own->id, $item, $conflicts, $changes, true);
                }

                continue;
            }

            $existing = isset($treePeople[$first], $treePeople[$second])
                ? $this->weddingOf($treePeople[$first], $treePeople[$second]->id)
                : null;

            if ($existing === null) {
                $wedding = new PlannedMarriage($marriage, $repoId);
                $planned[] = $wedding;

                if (isset($restoredEntries[$repoId])) {
                    $restorations[] = new PlannedRestoration($repoId, RepoKeyKind::Marriage, 'Ślub: '.$who, marriage: $wedding);
                }

                continue;
            }

            [$item] = $this->dateItem('marriage_date', 'Data ślubu', "{$repoId}/data", $who, $marriage->event->date, $existing, null);
            $planned[] = $this->plannedWedding($marriage, $repoId, $existing->id, $item, $conflicts, $changes, false);
        }

        return ['planned' => $planned, 'skipped' => $skipped, 'conflicts' => $conflicts, 'changes' => $changes, 'restorations' => $restorations, 'kept' => $kept, 'knownKeys' => $knownKeys];
    }

    /**
     * A wedding of the tree with the date item the three values asked for.
     *
     * @param  Item|null  $item
     * @param  list<ValueConflict>  $conflicts
     * @param  list<PlannedChange>  $changes
     */
    private function plannedWedding(ResearchMarriage $marriage, string $repoId, int $eventId, PlannedChange|PlannedFill|ValueConflict|null $item, array &$conflicts, array &$changes, bool $known): PlannedMarriage
    {
        if ($item instanceof ValueConflict) {
            $conflicts[] = $item;
        } elseif ($item instanceof PlannedChange) {
            $changes[] = $item;
        }

        return new PlannedMarriage(
            $marriage,
            $repoId,
            $eventId,
            $item instanceof PlannedFill ? $item : null,
            $item instanceof ValueConflict ? $item : null,
            $item instanceof PlannedChange ? $item : null,
            $known,
        );
    }

    /**
     * The lowest-id wedding of the person with the given spouse that no import has taken yet.
     */
    private function weddingOf(Person $person, int $spouseId): ?Event
    {
        return $person->events
            ->filter(fn (Event $event): bool => $event->type === EventType::Marriage && $event->repo_id === null && $event->participants->contains('id', $spouseId))
            ->sortBy('id')
            ->first();
    }

    /**
     * What the repo holds for the keys of a new person: the person, its birth and its death, as the next import compares it.
     *
     * @return array<string, array<string, string|null>>
     */
    private function snapshotsOf(ResearchPerson $research, ?string $note): array
    {
        $snapshots = [RepoKeys::person($research->id) => RepoValues::person($research, $note)];

        if ($research->birth !== null) {
            $snapshots[RepoKeys::birth($research->id)] = RepoValues::event($research->birth);
        }

        if ($research->death !== null) {
            $snapshots[RepoKeys::death($research->id)] = RepoValues::event($research->death);
        }

        return $snapshots;
    }

    /**
     * What the repo holds for the key of a person of the tree. A gender the rules of the tree left out keeps the value
     * of the last import, or has no entry when there was none, so that it comes back at the next import.
     *
     * @return array<string, string|null>
     */
    private function personValues(ResearchPerson $research, ?string $note, bool $skippedGender, ?RepoSnapshot $old): array
    {
        $values = RepoValues::person($research, $note, ! $skippedGender);

        if (! $skippedGender || $old === null || ! array_key_exists('plec', $old->values)) {
            return $values;
        }

        return ['imiona' => $values['imiona'], 'nazwisko' => $values['nazwisko'], 'plec' => $old->values['plec'], 'notatka' => $values['notatka']];
    }

    /**
     * What the author decided for the records the repo no longer has. Every key that goes loses its memory.
     *
     * @return array{0: list<PlannedRemoval>, 1: list<PlannedRemoval>, 2: list<string>} the removals and detaches, the detached births, the keys to forget
     */
    private function planRemovals(RepoRecordClasses $classes, ImportDecisions $decisions): array
    {
        $removals = [];
        $births = [];
        $forgotten = [];

        foreach ($classes->removedInRepo as $entry) {
            $decision = $decisions->removals[$entry->key];
            $memberKeys = array_map(fn (RepoRecord $member): string => $member->key, $entry->members);
            $removal = new PlannedRemoval(
                $entry->key,
                $entry->kind,
                $entry->record === null ? null : (int) $entry->record->getKey(),
                $decision,
                $decision === ImportDecisions::DELETE ? $entry->impact : null,
                $memberKeys,
            );

            if ($entry->detachOnly()) {
                $births[] = $removal;
            } else {
                $removals[] = $removal;
            }

            array_push($forgotten, $entry->key, ...$memberKeys);
        }

        return [$removals, $births, $forgotten];
    }

    /**
     * The repo values to remember for the records the author leaves deleted, so that they are not asked about again.
     *
     * @return array<string, array<string, string|null>>
     */
    private function keptDeletedSnapshots(RepoRecordClasses $classes, ImportDecisions $decisions, ResearchRepoReading $reading): array
    {
        $kept = [];

        foreach ($classes->deletedInApp as $entry) {
            if ($decisions->deletions[$entry->key] !== ImportDecisions::KEEP) {
                continue;
            }

            foreach ([$entry, ...$entry->members] as $each) {
                $kept[$each->key] = RepoValues::forKey($each->key, $reading) ?? [];
            }
        }

        return $kept;
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
