<?php

namespace App\Actions\Import;

use App\Actions\Persons\DeletePerson;
use App\Actions\Persons\GuardsParentSexChange;
use App\Actions\Persons\MapsEventInput;
use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParticipantRole;
use App\Enums\RepoKeyKind;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\NoteBlock;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedMatch;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedRemoval;
use App\Support\Import\PlannedRestoration;
use App\Support\Import\PlannedUpdate;
use App\Support\Import\RepoKeys;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ApplyImport
{
    use GuardsParentSexChange;
    use MapsEventInput;

    public function __construct(
        private CreateParentChildRelation $createRelation,
        private CreateMarriage $createMarriage,
        private DeletePerson $deletePerson,
        private ClassifyRepoRecords $classify,
    ) {}

    /**
     * Write an import plan into the tree, all or nothing. Any exception rolls the whole write back.
     *
     * Under the lock of the tree it first checks that every record and value the plan saw is still as the plan saw it.
     * Then, in this order: the records the repo dropped are deleted or detached, new, matched and restored persons are
     * written, the approved changes, fills and settled conflicts go into the persons the earlier import took, then the
     * pairs and weddings, and last the memory of this import. What the import does not name (photo, residences, sources,
     * places) stays as it is, and a record without a repo id is never deleted or changed behind the author's back.
     *
     * @throws RuntimeException when a record or value of the plan changed since planning, a key the plan creates is taken,
     *                          the memory of the last import moved, or a step breaks a rule of the app
     * @throws ValidationException when a new wedding breaks a rule of the app
     */
    public function handle(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): ImportResult
    {
        return DB::transaction(function () use ($tree, $plan, $approvals, $resolutions): ImportResult {
            Tree::whereKey($tree->id)->lockForUpdate()->firstOrFail();

            $locked = $this->lockPeople($tree, $plan);
            $this->verify($tree, $plan, $approvals, $resolutions, $locked);

            $removedPersons = 0;
            $removedRecords = 0;
            $detached = 0;

            foreach ($plan->removals as $removal) {
                if ($removal->decision === ImportDecisions::DETACH) {
                    $this->detach($tree, $removal);
                    $detached++;
                } elseif ($this->remove($tree, $removal, $locked)) {
                    $removal->kind === RepoKeyKind::Person ? $removedPersons++ : $removedRecords++;
                }
            }

            foreach ($plan->detachedBirths as $removal) {
                $this->detach($tree, $removal);
                $detached++;
            }

            /** @var array<string, Person> $people */
            $people = [];

            foreach ($tree->people()->whereNotNull('repo_id')->get() as $known) {
                $known->setRelation('tree', $tree);
                $people[(string) $known->repo_id] = $known;
            }

            $births = 0;
            $deaths = 0;
            $fills = 0;
            $taken = 0;

            foreach ($plan->matched as $match) {
                $person = $locked[$match->personId];
                $people[$match->person->id] = $person;

                try {
                    $written = $this->writeMatch($person->load('events'), $match, $resolutions);
                } catch (ValidationException $exception) {
                    throw $this->withContext("{$match->person->id} (#{$person->id})", $exception);
                }

                $fills += $written['fills'];
                $taken += $written['taken'];

                if ($match->createBirth && $match->person->birth !== null) {
                    $this->createEvent($tree, $person, EventType::Birth, $match->person->birth, $match->birthRepoId);
                    $births++;
                }

                if ($match->createDeath && $match->person->death !== null) {
                    $this->createEvent($tree, $person, EventType::Death, $match->person->death, $match->deathRepoId);
                    $deaths++;
                }
            }

            $newPersons = [...$plan->persons];

            foreach ($plan->restorations as $restoration) {
                if ($restoration->person !== null) {
                    $newPersons[] = $restoration->person;
                }
            }

            foreach ($newPersons as $planned) {
                $research = $planned->person;
                $people[$research->id] = $this->createPerson($tree, $planned, $births, $deaths);
            }

            foreach ($plan->restorations as $restoration) {
                if ($restoration->event === null || $restoration->personId === null) {
                    continue;
                }

                $type = $restoration->kind === RepoKeyKind::Birth ? EventType::Birth : EventType::Death;
                $person = $locked[$restoration->personId];

                if ($person->events()->where('type', $type)->exists()) {
                    throw new RuntimeException("Osoba {$person->displayName()} (#{$person->id}) dostała wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                $this->createEvent($tree, $person, $type, $restoration->event, $restoration->key);
                $type === EventType::Birth ? $births++ : $deaths++;
            }

            foreach ($plan->updates as $update) {
                try {
                    $written = $this->writeUpdate($tree, $locked[$update->personId]->load('events'), $update, $approvals, $resolutions);
                } catch (ValidationException $exception) {
                    throw $this->withContext("{$update->person->id} (#{$update->personId})", $exception);
                }

                $fills += $written['fills'];
                $taken += $written['taken'];
                $births += $written['births'];
                $deaths += $written['deaths'];
            }

            $newRelations = 0;
            $markedRelations = 0;

            foreach ($plan->relations as $planned) {
                $parent = $this->personFor($people, $planned->parentId);
                $child = $this->personFor($people, $planned->childId);

                if ($planned->existingId !== null) {
                    $relation = $tree->parentChildRelations()->whereKey($planned->existingId)->first();

                    if ($relation === null || $relation->parent_id !== $parent->id || $relation->child_id !== $child->id) {
                        throw new RuntimeException("Relacja {$planned->repoId} nie pasuje już do drzewa; nic nie zostało zapisane.");
                    }

                    $relation->update(['repo_id' => $planned->repoId]);
                    $markedRelations++;

                    continue;
                }

                try {
                    $relation = $this->createRelation->handle($tree, $parent, $child);
                } catch (ValidationException $exception) {
                    throw $this->withContext("{$planned->repoId} (#{$parent->id} → #{$child->id})", $exception);
                }

                $relation->update(['repo_id' => $planned->repoId]);
                $newRelations++;
            }

            $newMarriages = 0;
            $markedMarriages = 0;

            foreach ($plan->marriages as $planned) {
                [$first, $second] = $planned->marriage->personIds;
                $spouse = $this->personFor($people, $first);
                $partner = $this->personFor($people, $second);

                if ($planned->existingEventId !== null) {
                    $event = $tree->events()->whereKey($planned->existingEventId)->where('type', EventType::Marriage)->first();

                    if ($event === null || $event->participants()->whereKey([$spouse->id, $partner->id])->count() !== 2) {
                        throw new RuntimeException("Małżeństwo {$planned->repoId} nie pasuje już do drzewa; nic nie zostało zapisane.");
                    }

                    $date = null;
                    $clear = false;

                    if ($planned->dateFill !== null) {
                        $date = $planned->dateFill->value;
                        $fills++;
                    }

                    if ($planned->dateConflict !== null && $resolutions->takesRepo($planned->dateConflict->key)) {
                        $date = $planned->dateConflict->repoValue;
                        $clear = $date === null;
                        $taken++;
                    }

                    if ($planned->dateChange !== null && ! $approvals->rejects($planned->dateChange->key)) {
                        $date = $planned->dateChange->repoValue;
                        $clear = $date === null;
                    }

                    $this->markEvent($event, $planned->repoId, $date instanceof ResearchDate ? $date : null, $clear);
                    $markedMarriages += $planned->known ? 0 : 1;

                    continue;
                }

                $event = $this->createMarriage->handle(
                    $tree,
                    $spouse,
                    $partner,
                    $this->dateInput($planned->marriage->event->date),
                );
                $event->update(['repo_id' => $planned->repoId]);
                $newMarriages++;
            }

            $this->rememberSnapshots($tree, $plan);

            $changesRejected = count(array_filter($plan->changes, fn (PlannedChange $change): bool => $approvals->rejects($change->key)));

            return new ImportResult(
                persons: count($newPersons),
                births: $births,
                deaths: $deaths,
                marriages: $newMarriages,
                relations: $newRelations,
                events: $births + $deaths + $newMarriages,
                matched: count($plan->matched),
                fills: $fills,
                takenFromRepo: $taken,
                markedRelations: $markedRelations,
                markedMarriages: $markedMarriages,
                changesTaken: count($plan->changes) - $changesRejected,
                changesRejected: $changesRejected,
                removedPersons: $removedPersons,
                removedRecords: $removedRecords,
                detached: $detached,
                restored: count($plan->restorations),
                keptDeleted: $this->countKeptRecords(array_map(strval(...), array_keys($plan->keptDeletedSnapshots))),
            );
        });
    }

    /**
     * Leave the memory of this import: the repo values of every key the plan wrote or marked, the values of the keys the
     * author left deleted, and no memory of the keys it forgets. The query builder skips the casts of the model, so
     * `values` goes in as encoded JSON.
     */
    private function rememberSnapshots(Tree $tree, ImportPlan $plan): void
    {
        if ($plan->forgottenSnapshots !== []) {
            $tree->repoSnapshots()->whereIn('repo_id', $plan->forgottenSnapshots)->delete();
        }

        foreach ([[$plan->snapshots, false], [$plan->keptDeletedSnapshots, true]] as [$snapshots, $deletionKept]) {
            $rows = [];

            foreach ($snapshots as $key => $values) {
                $rows[] = [
                    'repo_id' => $key,
                    'values' => json_encode($values, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE),
                    'deletion_kept' => $deletionKept,
                ];
            }

            if ($rows !== []) {
                $tree->repoSnapshots()->upsert($rows, ['tree_id', 'repo_id'], ['values', 'deletion_kept']);
            }
        }
    }

    /**
     * Lock the persons the plan names in id order and check that each still belongs to the tree with the repo id the plan
     * saw: none for a matched person, the repo id for a known or a removed one.
     *
     * @return Collection<int, Person>
     *
     * @throws RuntimeException
     */
    private function lockPeople(Tree $tree, ImportPlan $plan): Collection
    {
        $matchedIds = array_map(fn (PlannedMatch $match): int => $match->personId, $plan->matched);

        if (count(array_unique($matchedIds)) !== count($matchedIds)) {
            throw new RuntimeException('Plan dopasowuje jedną osobę drzewa do kilku osób z repo; nic nie zostało zapisane.');
        }

        $removedIds = [];

        foreach ($plan->removals as $removal) {
            if ($removal->kind === RepoKeyKind::Person) {
                $removedIds[$removal->key] = $removal->recordId;
            }
        }

        $ids = [
            ...$matchedIds,
            ...array_map(fn (PlannedUpdate $update): int => $update->personId, $plan->updates),
            ...array_values(array_filter($removedIds, fn (?int $id): bool => $id !== null)),
            ...array_values(array_filter(array_map(fn (PlannedRestoration $restoration): ?int => $restoration->personId, $plan->restorations))),
        ];
        $ids = array_values(array_unique($ids));
        sort($ids);

        $people = $tree->people()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        foreach ($people as $person) {
            $person->setRelation('tree', $tree);
        }

        foreach ($matchedIds as $id) {
            $person = $people->get($id);

            if ($person === null) {
                throw new RuntimeException("Dopasowanej osoby #{$id} nie ma już w drzewie konta; nic nie zostało zapisane.");
            }

            if ($person->repo_id !== null) {
                throw new RuntimeException("Dopasowana osoba #{$id} ma już id z importu; nic nie zostało zapisane.");
            }
        }

        foreach ($plan->updates as $update) {
            $person = $people->get($update->personId);

            if ($person === null) {
                throw new RuntimeException("Osoby {$update->person->id} (#{$update->personId}) nie ma już w drzewie konta; nic nie zostało zapisane.");
            }

            if ($person->repo_id !== $update->person->id) {
                throw new RuntimeException("Osoba {$update->person->id} (#{$update->personId}) zmieniła się po planowaniu; nic nie zostało zapisane.");
            }
        }

        foreach ($removedIds as $key => $id) {
            $person = $id === null ? null : $people->get($id);

            if ($person === null || $person->repo_id !== $key) {
                throw new RuntimeException("Osoba {$key} zmieniła się po planowaniu; nic nie zostało zapisane.");
            }
        }

        foreach ($plan->restorations as $restoration) {
            if ($restoration->personId !== null && ! $people->has($restoration->personId)) {
                throw new RuntimeException("Osoby #{$restoration->personId}, której wydarzenie wraca z repo, nie ma już w drzewie konta; nic nie zostało zapisane.");
            }
        }

        return $people;
    }

    /**
     * The records the author chose to keep deleted: a key that travels with a kept person does not count on its own.
     *
     * @param  list<string>  $keys
     */
    private function countKeptRecords(array $keys): int
    {
        $keptPeople = array_values(array_filter($keys, fn (string $key): bool => RepoKeys::kindOf($key) === RepoKeyKind::Person));

        return count(array_filter($keys, function (string $key) use ($keptPeople): bool {
            if (RepoKeys::kindOf($key) === RepoKeyKind::Person) {
                return true;
            }

            return array_intersect(RepoKeys::personIdsOf($key), array_map(fn (string $person): string => RepoKeys::personIdsOf($person)[0], $keptPeople)) === [];
        }));
    }

    /**
     * Check, before anything is written, that everything the plan saw is still so: the values of the known persons and
     * weddings, the records to delete or detach, the keys to create, and the memory of the last import.
     *
     * @param  Collection<int, Person>  $locked
     *
     * @throws RuntimeException
     */
    private function verify(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions, Collection $locked): void
    {
        foreach ($plan->updates as $update) {
            $person = $locked[$update->personId]->load('events');

            foreach ($update->fills as $fill) {
                $this->guardPerson($person, $fill->field, $fill->appLabel);
            }

            foreach ($update->changes as $change) {
                if (! $approvals->rejects($change->key)) {
                    $this->guardPerson($person, $change->field, $change->appLabel);
                }
            }

            foreach ($update->conflicts as $conflict) {
                if ($resolutions->takesRepo($conflict->key)) {
                    $this->guardPerson($person, $conflict->field, $conflict->appLabel);
                }
            }
        }

        foreach ($plan->matched as $match) {
            $person = $locked[$match->personId]->load('events');

            foreach ($match->fills as $fill) {
                $this->guardPerson($person, $fill->field, $fill->appLabel);
            }

            foreach ($match->conflicts as $conflict) {
                if ($resolutions->takesRepo($conflict->key)) {
                    $this->guardPerson($person, $conflict->field, $conflict->appLabel);
                }
            }
        }

        foreach ($plan->marriages as $planned) {
            if ($planned->existingEventId === null) {
                continue;
            }

            $event = $tree->events()->whereKey($planned->existingEventId)->where('type', EventType::Marriage)->lockForUpdate()->first();

            if ($event === null) {
                throw new RuntimeException("Małżeństwo {$planned->repoId} nie pasuje już do drzewa; nic nie zostało zapisane.");
            }

            if ($planned->dateFill !== null) {
                $this->guardWedding($event, $planned->repoId, $planned->dateFill->appLabel);
            }

            if ($planned->dateConflict !== null && $resolutions->takesRepo($planned->dateConflict->key)) {
                $this->guardWedding($event, $planned->repoId, $planned->dateConflict->appLabel);
            }

            if ($planned->dateChange !== null && ! $approvals->rejects($planned->dateChange->key)) {
                $this->guardWedding($event, $planned->repoId, $planned->dateChange->appLabel);
            }
        }

        foreach ([...$plan->removals, ...$plan->detachedBirths] as $removal) {
            $this->guardRemoval($tree, $removal);
        }

        $this->guardFreeKeys($tree, $plan);
        $this->guardMemory($tree, $plan);
    }

    /**
     * Check that the record to delete or detach is still in the tree with the repo id the plan saw, and that a removal
     * still takes with it exactly what the author saw in the table of effects.
     *
     * @throws RuntimeException
     */
    private function guardRemoval(Tree $tree, PlannedRemoval $removal): void
    {
        $query = match ($removal->kind) {
            RepoKeyKind::Person => $tree->people(),
            RepoKeyKind::Relation => $tree->parentChildRelations(),
            default => $tree->events(),
        };

        $record = $removal->recordId === null ? null : $query->whereKey($removal->recordId)->where('repo_id', $removal->key)->first();

        if ($record === null) {
            throw new RuntimeException("Rekord {$removal->key} zmienił się po planowaniu; nic nie zostało zapisane.");
        }

        if ($removal->decision === ImportDecisions::DELETE && $removal->impact !== null
            && $this->classify->impactOf($tree, $removal->key, $record) != $removal->impact) {
            throw new RuntimeException("Skutki usunięcia {$removal->key} zmieniły się po planowaniu; nic nie zostało zapisane.");
        }
    }

    /**
     * Check that no record of the tree holds a key the plan is about to create.
     *
     * @throws RuntimeException
     */
    private function guardFreeKeys(Tree $tree, ImportPlan $plan): void
    {
        $people = [];
        $events = [];
        $relations = [];

        foreach ([...$plan->persons, ...array_filter(array_map(fn (PlannedRestoration $restoration): ?PlannedPerson => $restoration->person, $plan->restorations))] as $planned) {
            $people[] = $planned->person->id;
            array_push($events, ...array_filter([$planned->birthRepoId, $planned->deathRepoId]));
        }

        foreach ([...$plan->matched, ...$plan->updates] as $planned) {
            if ($planned->createBirth && $planned->birthRepoId !== null) {
                $events[] = $planned->birthRepoId;
            }

            if ($planned->createDeath && $planned->deathRepoId !== null) {
                $events[] = $planned->deathRepoId;
            }
        }

        foreach ($plan->restorations as $restoration) {
            if ($restoration->event !== null) {
                $events[] = $restoration->key;
            }
        }

        foreach ($plan->relations as $planned) {
            if ($planned->existingId === null) {
                $relations[] = $planned->repoId;
            }
        }

        foreach ($plan->marriages as $planned) {
            if ($planned->existingEventId === null) {
                $events[] = $planned->repoId;
            }
        }

        foreach ([[$tree->people(), $people], [$tree->events(), $events], [$tree->parentChildRelations(), $relations]] as [$query, $keys]) {
            if ($keys === []) {
                continue;
            }

            $taken = $query->whereIn('repo_id', $keys)->value('repo_id');

            if ($taken !== null) {
                throw new RuntimeException("Klucz repo {$taken} pojawił się w drzewie po planowaniu; nic nie zostało zapisane.");
            }
        }
    }

    /**
     * Check that the memory entries the write touches are the ones the plan saw.
     *
     * @throws RuntimeException
     */
    private function guardMemory(Tree $tree, ImportPlan $plan): void
    {
        $keys = array_values(array_unique([...array_map(strval(...), array_keys($plan->snapshots)), ...array_map(strval(...), array_keys($plan->keptDeletedSnapshots)), ...$plan->forgottenSnapshots]));

        if ($keys === []) {
            return;
        }

        $current = [];

        foreach ($tree->repoSnapshots()->whereIn('repo_id', $keys)->orderBy('id')->lockForUpdate()->get() as $snapshot) {
            $current[$snapshot->repo_id] = $snapshot->fingerprint();
        }

        if ($current != $plan->seenSnapshots) {
            throw new RuntimeException('Pamięć ostatniego importu zmieniła się po planowaniu; nic nie zostało zapisane.');
        }
    }

    /**
     * Delete a record the repo dropped: a person with everything `DeletePerson` takes along, a death with the burial of its
     * person, a pair or a wedding. A record an earlier removal already took along is skipped.
     *
     * @param  Collection<int, Person>  $locked
     * @return bool whether a record was deleted
     */
    private function remove(Tree $tree, PlannedRemoval $removal, Collection $locked): bool
    {
        switch ($removal->kind) {
            case RepoKeyKind::Person:
                $person = $locked->get((int) $removal->recordId);

                if ($person === null || ! $person->exists) {
                    return false;
                }

                $this->deletePerson->handle($person);

                return true;
            case RepoKeyKind::Relation:
                $relation = $tree->parentChildRelations()->whereKey($removal->recordId)->first();
                $relation?->delete();

                return $relation !== null;
            default:
                $event = $tree->events()->whereKey($removal->recordId)->first();

                if ($event === null) {
                    return false;
                }

                if ($removal->kind === RepoKeyKind::Death) {
                    $event->participants()->get()->each(fn (Person $person) => $person->load('events')->burial()?->delete());
                }

                $event->delete();

                return true;
        }
    }

    /**
     * Forget the repo id of a record, and of the keys that went the same way with it, and keep everything else.
     */
    private function detach(Tree $tree, PlannedRemoval $removal): void
    {
        $keys = [$removal->key, ...$removal->memberKeys];

        $tree->people()->whereIn('repo_id', $keys)->update(['repo_id' => null]);
        $tree->events()->whereIn('repo_id', $keys)->update(['repo_id' => null]);
        $tree->parentChildRelations()->whereIn('repo_id', $keys)->update(['repo_id' => null]);
    }

    /**
     * Create a new or restored person with its birth and death.
     */
    private function createPerson(Tree $tree, PlannedPerson $planned, int &$births, int &$deaths): Person
    {
        $research = $planned->person;

        $person = $tree->people()->create([
            'first_name' => $research->givenNames,
            'last_name' => $research->surname,
            'gender' => $research->gender,
            'note' => $planned->note,
            'repo_id' => $research->id,
        ]);
        $person->setRelation('tree', $tree);

        if ($research->birth !== null) {
            $this->createEvent($tree, $person, EventType::Birth, $research->birth, $planned->birthRepoId);
            $births++;
        }

        if ($research->death !== null) {
            $this->createEvent($tree, $person, EventType::Death, $research->death, $planned->deathRepoId);
            $deaths++;
        }

        return $person;
    }

    /**
     * Write the approved changes, the fills and the conflicts settled for the repo into a person an earlier import took,
     * and mark, create or change its birth and death.
     *
     * @return array{fills: int, taken: int, births: int, deaths: int}
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    private function writeUpdate(Tree $tree, Person $person, PlannedUpdate $update, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        /** @var list<array{0: string, 1: string|Gender|ResearchDate|null}> $values */
        $values = [];
        $fills = 0;
        $taken = 0;

        foreach ($update->fills as $fill) {
            $values[] = [$fill->field, $fill->value];
            $fills++;
        }

        foreach ($update->changes as $change) {
            if (! $approvals->rejects($change->key)) {
                $values[] = [$change->field, $change->repoValue];
            }
        }

        foreach ($update->conflicts as $conflict) {
            if ($resolutions->takesRepo($conflict->key)) {
                $values[] = [$conflict->field, $conflict->repoValue];
                $taken++;
            }
        }

        $attributes = [];
        $dates = [];

        foreach ($values as [$field, $value]) {
            match (true) {
                in_array($field, ['first_name', 'last_name'], true) && ($value === null || is_string($value)) => $attributes[$field] = $value,
                $field === 'gender' && ($value === null || $value instanceof Gender) => $attributes[$field] = $value,
                $field === 'note' && ($value === null || is_string($value)) => $attributes[$field] = NoteBlock::replace($person->note, $update->person->id, $value),
                in_array($field, ['birth_date', 'death_date'], true) && ($value === null || $value instanceof ResearchDate) => $dates[$field] = $value,
                default => throw new RuntimeException("Plan ma niewłaściwą wartość pola {$field} dla osoby #{$person->id}."),
            };
        }

        if (array_key_exists('gender', $attributes)) {
            $this->guardParentSexChange($person, $attributes['gender']);
        }

        if ($attributes !== []) {
            $person->update($attributes);
        }

        $births = 0;
        $deaths = 0;
        $research = $update->person;

        foreach ([
            [EventType::Birth, $research->birth, RepoKeys::birth($research->id), $update->birthRepoId, $update->createBirth, 'birth_date'],
            [EventType::Death, $research->death, RepoKeys::death($research->id), $update->deathRepoId, $update->createDeath, 'death_date'],
        ] as [$type, $repoEvent, $key, $markKey, $create, $dateField]) {
            if ($create) {
                if ($person->events->contains(fn (Event $event): bool => $event->type === $type)) {
                    throw new RuntimeException("Osoba {$person->displayName()} (#{$person->id}) dostała wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                if ($repoEvent !== null) {
                    $this->createEvent($tree, $person, $type, $repoEvent, $markKey);
                    $type === EventType::Birth ? $births++ : $deaths++;
                }

                continue;
            }

            if ($markKey !== null) {
                $event = $person->events->first(fn (Event $each): bool => $each->type === $type && $each->repo_id === null);

                if ($event === null) {
                    throw new RuntimeException("Osobie {$person->displayName()} (#{$person->id}) zniknęło wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                $this->markEvent($event, $markKey, $dates[$dateField] ?? null);

                continue;
            }

            if (array_key_exists($dateField, $dates)) {
                $event = $person->events->first(fn (Event $each): bool => $each->type === $type && $each->repo_id === $key);

                if ($event === null) {
                    throw new RuntimeException("Osobie {$person->displayName()} (#{$person->id}) zniknęło wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                $this->markEvent($event, $key, $dates[$dateField], $dates[$dateField] === null);
            }
        }

        return ['fills' => $fills, 'taken' => $taken, 'births' => $births, 'deaths' => $deaths];
    }

    /**
     * Write the repo id, the approved values and the note block into a matched person and mark its existing birth and
     * death.
     *
     * @return array{fills: int, taken: int} the values the repo added to empty fields and the conflicts settled for the repo
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    private function writeMatch(Person $person, PlannedMatch $match, ConflictResolutions $resolutions): array
    {
        /** @var list<array{0: string, 1: string|Gender|ResearchDate|null, 2: bool}> $changes */
        $changes = [];

        foreach ($match->fills as $fill) {
            $changes[] = [$fill->field, $fill->value, false];
        }

        foreach ($match->conflicts as $conflict) {
            if ($resolutions->takesRepo($conflict->key)) {
                $changes[] = [$conflict->field, $conflict->repoValue, true];
            }
        }

        $attributes = ['repo_id' => $match->person->id];
        $dates = [];
        $fills = 0;
        $taken = 0;

        foreach ($changes as [$field, $value, $fromRepo]) {
            match (true) {
                in_array($field, ['first_name', 'last_name'], true) && is_string($value) => $attributes[$field] = $value,
                $field === 'gender' && $value instanceof Gender => $attributes[$field] = $value,
                in_array($field, ['birth_date', 'death_date'], true) && $value instanceof ResearchDate => $dates[$field] = $value,
                default => throw new RuntimeException("Plan ma niewłaściwą wartość pola {$field} dla osoby #{$person->id}."),
            };

            $fromRepo ? $taken++ : $fills++;
        }

        if (isset($attributes['gender'])) {
            $this->guardParentSexChange($person, $attributes['gender']);
        }

        if ($match->note !== null) {
            $attributes['note'] = NoteBlock::replace($person->note, $match->person->id, $match->note);
        }

        $person->update($attributes);

        foreach ([[EventType::Birth, $match->birthRepoId, $match->createBirth, 'birth_date'], [EventType::Death, $match->deathRepoId, $match->createDeath, 'death_date']] as [$type, $repoId, $create, $dateField]) {
            $event = $type === EventType::Birth ? $person->birth() : $person->death();

            if ($create) {
                if ($event !== null) {
                    throw new RuntimeException("Dopasowana osoba #{$person->id} dostała wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                continue;
            }

            if ($repoId === null) {
                continue;
            }

            if ($event === null) {
                throw new RuntimeException("Dopasowanej osobie #{$person->id} zniknęło wydarzenie po planowaniu; nic nie zostało zapisane.");
            }

            $this->markEvent($event, $repoId, $dates[$dateField] ?? null);
        }

        return ['fills' => $fills, 'taken' => $taken];
    }

    /**
     * Check that a field of the locked person still holds what the plan saw: empty for a fill, the value shown to the
     * author for a change or a conflict settled for the repo.
     *
     * @param  string|null  $planned  the label the plan recorded, `null` or "—" for an empty field
     *
     * @throws RuntimeException
     */
    private function guardPerson(Person $person, string $field, ?string $planned): void
    {
        $planned = $planned === PlanImport::EMPTY_LABEL ? null : $planned;
        $current = match ($field) {
            'first_name' => PlanImport::cleanText($person->first_name),
            'last_name' => PlanImport::cleanText($person->last_name),
            'gender' => $person->gender?->label(),
            'note' => $person->repo_id === null ? null : NoteBlock::find($person->note, $person->repo_id),
            'birth_date' => ResearchDate::fromEvent($person->birth())?->label(),
            'death_date' => ResearchDate::fromEvent($person->death())?->label(),
            default => throw new RuntimeException("Plan ma niewłaściwe pole {$field} dla osoby #{$person->id}."),
        };

        if ($current !== $planned) {
            throw new RuntimeException("Osoba {$person->displayName()} (#{$person->id}) zmieniła się po planowaniu; nic nie zostało zapisane.");
        }
    }

    /**
     * Check that the date of an existing wedding is still the one the plan saw.
     *
     * @param  string|null  $planned  the label the plan recorded, `null` for a wedding without a date
     *
     * @throws RuntimeException
     */
    private function guardWedding(Event $event, string $repoId, ?string $planned): void
    {
        $planned = $planned === PlanImport::EMPTY_LABEL ? null : $planned;

        if (ResearchDate::fromEvent($event)?->label() !== $planned) {
            throw new RuntimeException("Małżeństwo {$repoId} zmieniło się po planowaniu; nic nie zostało zapisane.");
        }
    }

    /**
     * A failed rule of the app as a rollback message that says which record of the plan it came from.
     */
    private function withContext(string $context, ValidationException $exception): RuntimeException
    {
        return new RuntimeException($context.': '.implode(' ', Arr::flatten($exception->errors())), 0, $exception);
    }

    /**
     * Give an existing event its repo id and, when given, its date, or empty its date when the repo cleared it. Only the date columns change: the place, the
     * cemetery, the wedding form and the linked sources stay. Saved through the model so that the derived flag follows.
     */
    private function markEvent(Event $event, string $repoId, ?ResearchDate $date, bool $clear = false): void
    {
        $attributes = ['repo_id' => $repoId];

        if ($clear) {
            $attributes += Arr::only(
                $this->eventAttributesFrom($this->dateInput(null)),
                ['date_year', 'date_month', 'date_day', 'date_qualifier', 'date_until_year'],
            );
        } elseif ($date !== null) {
            $attributes += Arr::only(
                $this->eventAttributesFrom($this->dateInput($date)),
                ['date_year', 'date_month', 'date_day', 'date_qualifier', 'date_until_year'],
            );
        }

        $event->fill($attributes)->save();
    }

    private function createEvent(Tree $tree, Person $person, EventType $type, ResearchEvent $research, ?string $repoId): void
    {
        $event = $tree->events()->create([
            'type' => $type,
            ...$this->eventAttributesFrom($this->dateInput($research->date)),
            'repo_id' => $repoId,
        ]);

        $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    }

    /**
     * @return array{year?: int, month?: int|null, day?: int|null, qualifier?: DateQualifier, until_year?: int|null}
     */
    private function dateInput(?ResearchDate $date): array
    {
        if ($date === null) {
            return [];
        }

        return [
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'qualifier' => $date->qualifier,
            'until_year' => $date->untilYear,
        ];
    }

    /**
     * @param  array<string, Person>  $people
     */
    private function personFor(array $people, string $repoId): Person
    {
        return $people[$repoId] ?? throw new RuntimeException("Plan odwołuje się do osoby {$repoId}, której nie ma w planie.");
    }
}
