<?php

namespace App\Actions\Import;

use App\Actions\Cemeteries\StoreCemetery;
use App\Actions\Localities\StoreLocality;
use App\Actions\Parishes\StoreParish;
use App\Actions\Persons\DeletePerson;
use App\Actions\Persons\GuardsParentSexChange;
use App\Actions\Persons\MapsEventInput;
use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Enums\ConfidenceLevel;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParticipantRole;
use App\Enums\RepoKeyKind;
use App\Enums\SourceKind;
use App\Enums\SourceRecordType;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\CopiedFiles;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\NoteBlock;
use App\Support\Import\PlaceName;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedMatch;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedPlace;
use App\Support\Import\PlannedRemoval;
use App\Support\Import\PlannedRestoration;
use App\Support\Import\PlannedSource;
use App\Support\Import\PlannedSourceMerge;
use App\Support\Import\PlannedUpdate;
use App\Support\Import\RepoKeys;
use App\Support\Import\ResearchDate;
use App\Support\Import\ResearchEvent;
use App\Support\Import\ResearchSourceLink;
use App\Support\Import\SourceLinks;
use App\Support\Import\SurnameVariants;
use App\Support\Import\ValueConflict;
use App\Support\Import\WrittenPlaces;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ApplyImport
{
    use GuardsParentSexChange;
    use MapsEventInput;

    public function __construct(
        private CreateParentChildRelation $createRelation,
        private CreateMarriage $createMarriage,
        private DeletePerson $deletePerson,
        private ClassifyRepoRecords $classify,
        private StoreLocality $storeLocality,
        private StoreCemetery $storeCemetery,
        private StoreParish $storeParish,
        private WriteResearchSources $sources,
    ) {}

    /**
     * Write an import plan into the tree, all or nothing. Any exception rolls the whole write back.
     *
     * Under the lock of the tree it first checks that every record and value the plan saw is still as the plan saw it.
     * Then, in this order: the records the repo dropped are deleted or detached, the new localities, variants and
     * cemeteries are added (only the localities and cemeteries that an item written after the approvals and the settled
     * conflicts uses, the variants always), new, matched and restored persons are written, the approved changes, fills and
     * settled conflicts go into the persons the earlier import took, then the pairs and weddings, and last the memory of
     * this import. The place of an event changes only as the plan says, and a burial always gets its cemetery and the
     * locality of that cemetery together. The parishes the plan adds follow the localities, and the sources of the repo
     * (new, joined, changed, restored) are written right after the places; their links to events are written last, when
     * every event of the write exists, just before the notes. What the import does not name (photo, residences) stays as
     * it is, and a record without a repo id is never deleted or changed behind the author's back.
     *
     * The scans the write copies are deleted again when the transaction rolls back; the files of a replaced or removed
     * source are deleted after the commit.
     *
     * @throws RuntimeException when a record or value of the plan changed since planning, a key the plan creates is taken,
     *                          the memory of the last import moved, or a step breaks a rule of the app
     * @throws ValidationException when a new wedding breaks a rule of the app
     */
    public function handle(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): ImportResult
    {
        $copied = new CopiedFiles;

        try {
            return $this->write($tree, $plan, $approvals, $resolutions, $copied);
        } catch (Throwable $exception) {
            if ($copied->rolledBack() && $copied->paths() !== [] && ! Storage::disk('local')->delete($copied->paths())) {
                report(new RuntimeException('Unable to delete copied source file(s) ['.implode(', ', $copied->paths()).'].'));
            }

            throw $exception;
        }
    }

    /**
     * The transaction of {@see handle()}.
     *
     * @throws Throwable
     */
    private function write(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions, CopiedFiles $copied): ImportResult
    {
        return DB::transaction(function () use ($tree, $plan, $approvals, $resolutions, $copied): ImportResult {
            Tree::whereKey($tree->id)->lockForUpdate()->firstOrFail();

            $locked = $this->lockPeople($tree, $plan);
            $this->verify($tree, $plan, $approvals, $resolutions, $locked);

            $removedPersons = 0;
            $removedRecords = 0;
            $detached = 0;
            $removedSources = 0;
            $detachedSources = 0;

            foreach ($plan->removals as $removal) {
                if ($removal->decision === ImportDecisions::DETACH) {
                    $this->detach($tree, $removal);
                    $removal->kind === RepoKeyKind::Source ? $detachedSources++ : $detached++;
                } elseif ($this->remove($tree, $removal, $locked)) {
                    match ($removal->kind) {
                        RepoKeyKind::Person => $removedPersons++,
                        RepoKeyKind::Source => $removedSources++,
                        default => $removedRecords++,
                    };
                }
            }

            foreach ($plan->detachedBirths as $removal) {
                $this->detach($tree, $removal);
                $detached++;
            }

            [$written, $aliasesAdded] = $this->writePlaces($tree, $plan, $approvals, $resolutions);
            $sourceResult = $this->sources->handle($tree, $plan, $approvals, $resolutions, $written, $copied);

            /** @var array<string, Person> $people */
            $people = [];

            foreach ($tree->people()->whereNotNull('repo_id')->get() as $known) {
                $known->setRelation('tree', $tree);
                $people[(string) $known->repo_id] = $known;
            }

            $births = 0;
            $deaths = 0;
            $burials = 0;
            $fills = 0;
            $taken = 0;

            foreach ($plan->matched as $match) {
                $person = $locked[$match->personId];
                $people[$match->person->id] = $person;

                try {
                    $result = $this->writeMatch($tree, $person->load('events'), $match, $resolutions, $written);
                } catch (ValidationException $exception) {
                    throw $this->withContext("{$match->person->id} (#{$person->id})", $exception);
                }

                $fills += $result['fills'];
                $taken += $result['taken'];

                if ($match->createBirth && $match->person->birth !== null) {
                    $this->createEvent($tree, $person, EventType::Birth, $match->person->birth, $match->birthRepoId, $match->birthPlace, $written);
                    $births++;
                }

                if ($match->createDeath && $match->person->death !== null) {
                    $this->createEvent($tree, $person, EventType::Death, $match->person->death, $match->deathRepoId, $match->deathPlace, $written);
                    $deaths++;
                }

                if ($match->createBurial && $match->person->burial !== null) {
                    $this->createEvent($tree, $person, EventType::Burial, $match->person->burial, $match->burialRepoId, $match->burialPlace, $written);
                    $burials++;
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
                $people[$research->id] = $this->createPerson($tree, $planned, $written, $births, $deaths, $burials);
            }

            foreach ($plan->restorations as $restoration) {
                if ($restoration->event === null || $restoration->personId === null) {
                    continue;
                }

                $type = match ($restoration->kind) {
                    RepoKeyKind::Birth => EventType::Birth,
                    RepoKeyKind::Death => EventType::Death,
                    RepoKeyKind::Burial => EventType::Burial,
                    default => throw new RuntimeException("Plan przywraca wydarzenie {$restoration->key}, którego rodzaju nie zna."),
                };
                $person = $locked[$restoration->personId];

                if ($person->events()->where('type', $type)->exists()) {
                    throw new RuntimeException("Osoba {$person->displayName()} (#{$person->id}) dostała wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                $this->createEvent($tree, $person, $type, $restoration->event, $restoration->key, $restoration->place, $written);

                match ($type) {
                    EventType::Birth => $births++,
                    EventType::Death => $deaths++,
                    default => $burials++,
                };
            }

            foreach ($plan->updates as $update) {
                try {
                    $result = $this->writeUpdate($tree, $locked[$update->personId]->load('events'), $update, $approvals, $resolutions, $written);
                } catch (ValidationException $exception) {
                    throw $this->withContext("{$update->person->id} (#{$update->personId})", $exception);
                }

                $fills += $result['fills'];
                $taken += $result['taken'];
                $births += $result['births'];
                $deaths += $result['deaths'];
                $burials += $result['burials'];
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
                    $place = null;
                    $setPlace = false;
                    $level = null;
                    $setLevel = false;

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

                    if ($planned->placeFill !== null) {
                        $place = $planned->placeFill->value;
                        $setPlace = true;
                        $fills++;
                    }

                    if ($planned->confidenceFill !== null) {
                        $level = $planned->confidenceFill->value;
                        $setLevel = true;
                        $fills++;
                    }

                    if ($planned->confidenceConflict !== null && $resolutions->takesRepo($planned->confidenceConflict->key)) {
                        $level = $planned->confidenceConflict->repoValue;
                        $setLevel = true;
                        $taken++;
                    }

                    if ($planned->confidenceChange !== null && ! $approvals->rejects($planned->confidenceChange->key)) {
                        $level = $planned->confidenceChange->repoValue;
                        $setLevel = true;
                    }

                    $fills += $planned->sourceFill === null ? 0 : 1;
                    $taken += $planned->sourceConflict !== null && $resolutions->takesRepo($planned->sourceConflict->key) ? 1 : 0;

                    if ($planned->placeConflict !== null && $resolutions->takesRepo($planned->placeConflict->key)) {
                        $place = $planned->placeConflict->repoValue;
                        $setPlace = true;
                        $taken++;
                    }

                    if ($planned->placeChange !== null && ! $approvals->rejects($planned->placeChange->key)) {
                        $place = $planned->placeChange->repoValue;
                        $setPlace = true;
                    }

                    $placeAttributes = $setPlace ? $this->placeAttributes($tree, false, $place instanceof PlannedPlace ? $place : null, $place === null, $written) : [];

                    $this->markEvent($event, $planned->repoId, $date instanceof ResearchDate ? $date : null, $clear, $placeAttributes, $setLevel, $level instanceof ConfidenceLevel ? $level : null);
                    $markedMarriages += $planned->known ? 0 : 1;

                    continue;
                }

                $event = $this->createMarriage->handle(
                    $tree,
                    $spouse,
                    $partner,
                    [...$this->dateInput($planned->marriage->event->date), 'locality_id' => $planned->place === null ? null : $written->localityId($planned->place), 'confidence' => $planned->marriage->event->level],
                );
                $event->update(['repo_id' => $planned->repoId]);
                $newMarriages++;
            }

            [$linksAdded, $linksRemoved] = $this->sources->link($tree, $plan, $approvals, $resolutions);
            $answeredNotes = $this->writeAnsweredNotes($tree, $plan, $approvals, $resolutions);
            $this->rememberSnapshots($tree, $plan, $written, $answeredNotes);

            $changesRejected = count(array_filter($plan->changes, fn (PlannedChange $change): bool => $approvals->rejects($change->key)));
            $copied->bodyFinished();

            return new ImportResult(
                persons: count($newPersons),
                births: $births,
                deaths: $deaths,
                marriages: $newMarriages,
                relations: $newRelations,
                events: $births + $deaths + $burials + $newMarriages,
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
                burials: $burials,
                newLocalities: count($written->localities),
                newCemeteries: count($written->cemeteries),
                aliases: $aliasesAdded,
                newParishes: count($written->parishes),
                newSources: $sourceResult->created,
                mergedSources: $sourceResult->merged,
                changedSources: $sourceResult->changed,
                replacedFiles: $sourceResult->replaced,
                removedSources: $removedSources,
                detachedSources: $detachedSources,
                linksAdded: $linksAdded,
                linksRemoved: $linksRemoved,
            );
        });
    }

    /**
     * Leave the memory of this import: the repo values of every key the plan wrote or marked, the values of the keys the
     * author left deleted, and no memory of the keys it forgets. The query builder skips the casts of the model, so
     * `values` goes in as encoded JSON. A place the plan adds is remembered by the id the write gave it, and a place the
     * write did not add by none. A note block rendered again with the author's answers replaces the planned one.
     *
     * @param  array<string, string|null>  $answeredNotes  repo id => the note block the answers give, where it differs from the planned one
     */
    private function rememberSnapshots(Tree $tree, ImportPlan $plan, WrittenPlaces $written, array $answeredNotes = []): void
    {
        if ($plan->forgottenSnapshots !== []) {
            $tree->repoSnapshots()->whereIn('repo_id', $plan->forgottenSnapshots)->delete();
        }

        foreach ([[$plan->snapshots, false], [$plan->keptDeletedSnapshots, true]] as [$snapshots, $deletionKept]) {
            $rows = [];

            foreach ($snapshots as $key => $values) {
                if (array_key_exists((string) $key, $answeredNotes) && array_key_exists('notatka', $values)) {
                    $values['notatka'] = $answeredNotes[(string) $key];
                }

                foreach (['miejsce_id', 'cmentarz_id', 'parafia_id'] as $idField) {
                    if (array_key_exists($idField, $values)) {
                        $values[$idField] = $written->token($values[$idField]);
                    }
                }

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
     * Render again the note blocks whose place text waited for the author's answers: a rejected place change keeps the
     * repo text, a conflict settled for the repo drops it. A person whose block is the one the plan showed (written by
     * this import or already equal) gets the answered block; a block the author kept from the app stays.
     *
     * @return array<string, string|null> repo id => the answered block, only where it differs from the planned one
     */
    private function writeAnsweredNotes(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        $answered = [];

        foreach ($plan->notes as $repoId => $note) {
            $repoId = (string) $repoId;
            $planned = $note->planned();
            $block = $note->answered($approvals, $resolutions);

            if ($block === $planned) {
                continue;
            }

            $answered[$repoId] = $block;
            $person = $tree->people()->where('repo_id', $repoId)->first();

            if ($person !== null && NoteBlock::find($person->note, $repoId) === $planned) {
                $person->update(['note' => NoteBlock::replace($person->note, $repoId, $block)]);
            }
        }

        return $answered;
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

        $people = $tree->people()->with('surnameVariants')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

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
        $this->guardPlaces($tree, $plan);
        $skip = $this->unlinkedSources($plan);

        foreach ($plan->updates as $update) {
            $person = $locked[$update->personId]->load('events');

            foreach ($update->fills as $fill) {
                $this->guardPerson($tree, $person, $fill->field, $fill->appLabel, $skip);
            }

            foreach ($update->changes as $change) {
                if (! $approvals->rejects($change->key)) {
                    $this->guardPerson($tree, $person, $change->field, $change->appLabel, $skip);
                }
            }

            foreach ($update->conflicts as $conflict) {
                if ($resolutions->takesRepo($conflict->key)) {
                    $this->guardPerson($tree, $person, $conflict->field, $conflict->appLabel, $skip);
                }
            }
        }

        foreach ($plan->matched as $match) {
            $person = $locked[$match->personId]->load('events');

            foreach ($match->fills as $fill) {
                $this->guardPerson($tree, $person, $fill->field, $fill->appLabel, $skip);
            }

            foreach ($match->conflicts as $conflict) {
                if ($resolutions->takesRepo($conflict->key)) {
                    $this->guardPerson($tree, $person, $conflict->field, $conflict->appLabel, $skip);
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

            if ($planned->placeFill !== null) {
                $this->guardWeddingPlace($tree, $event, $planned->repoId, $planned->placeFill->appLabel);
            }

            if ($planned->placeConflict !== null && $resolutions->takesRepo($planned->placeConflict->key)) {
                $this->guardWeddingPlace($tree, $event, $planned->repoId, $planned->placeConflict->appLabel);
            }

            if ($planned->placeChange !== null && ! $approvals->rejects($planned->placeChange->key)) {
                $this->guardWeddingPlace($tree, $event, $planned->repoId, $planned->placeChange->appLabel);
            }

            if ($planned->confidenceFill !== null) {
                $this->guardWeddingConfidence($event, $planned->repoId, $planned->confidenceFill->appLabel);
            }

            if ($planned->confidenceConflict !== null && $resolutions->takesRepo($planned->confidenceConflict->key)) {
                $this->guardWeddingConfidence($event, $planned->repoId, $planned->confidenceConflict->appLabel);
            }

            if ($planned->confidenceChange !== null && ! $approvals->rejects($planned->confidenceChange->key)) {
                $this->guardWeddingConfidence($event, $planned->repoId, $planned->confidenceChange->appLabel);
            }

            if ($planned->sourceFill !== null) {
                $this->guardWeddingSources($event, $planned->repoId, $planned->sourceFill->appLabel, $skip);
            }

            if ($planned->sourceConflict !== null && $resolutions->takesRepo($planned->sourceConflict->key)) {
                $this->guardWeddingSources($event, $planned->repoId, $planned->sourceConflict->appLabel, $skip);
            }

            if ($planned->sourceChange !== null && ! $approvals->rejects($planned->sourceChange->key)) {
                $this->guardWeddingSources($event, $planned->repoId, $planned->sourceChange->appLabel, $skip);
            }
        }

        foreach ([...$plan->removals, ...$plan->detachedBirths] as $removal) {
            $this->guardRemoval($tree, $removal);
        }

        $this->sources->verify($tree, $plan, $approvals, $resolutions);
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
            RepoKeyKind::Source => $tree->sources(),
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
        $sources = [
            ...array_map(fn (PlannedSource $planned): string => $planned->source->id, $plan->newSources),
            ...array_map(fn (PlannedSourceMerge $merge): string => $merge->source->id, $plan->mergedSources),
            ...array_map(fn (PlannedRestoration $restoration): string => $restoration->key, array_filter($plan->restorations, fn (PlannedRestoration $restoration): bool => $restoration->source !== null)),
        ];

        foreach ([...$plan->persons, ...array_filter(array_map(fn (PlannedRestoration $restoration): ?PlannedPerson => $restoration->person, $plan->restorations))] as $planned) {
            $people[] = $planned->person->id;
            array_push($events, ...array_filter([$planned->birthRepoId, $planned->deathRepoId, $planned->burialRepoId]));
        }

        foreach ([...$plan->matched, ...$plan->updates] as $planned) {
            if ($planned->createBirth && $planned->birthRepoId !== null) {
                $events[] = $planned->birthRepoId;
            }

            if ($planned->createDeath && $planned->deathRepoId !== null) {
                $events[] = $planned->deathRepoId;
            }

            if ($planned->createBurial && $planned->burialRepoId !== null) {
                $events[] = $planned->burialRepoId;
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

        foreach ([[$tree->people(), $people], [$tree->events(), $events], [$tree->parentChildRelations(), $relations], [$tree->sources(), $sources]] as [$query, $keys]) {
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
     * person, a burial alone, a birth, a pair or a wedding. A record an earlier removal already took along is skipped.
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
            case RepoKeyKind::Burial:
                $burial = $tree->events()->whereKey($removal->recordId)->where('type', EventType::Burial)->first();
                $burial?->delete();

                return $burial !== null;
            case RepoKeyKind::Source:
                return $this->sources->remove($tree, $removal);
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
        $tree->sources()->whereIn('repo_id', $keys)->update(['repo_id' => null]);
    }

    /**
     * Create a new or restored person with its birth, death and burial.
     */
    private function createPerson(Tree $tree, PlannedPerson $planned, WrittenPlaces $written, int &$births, int &$deaths, int &$burials): Person
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
        $this->replaceSurnameVariants($person, SurnameVariants::fromResearch($research));

        if ($research->birth !== null) {
            $this->createEvent($tree, $person, EventType::Birth, $research->birth, $planned->birthRepoId, $planned->birthPlace, $written);
            $births++;
        }

        if ($research->death !== null) {
            $this->createEvent($tree, $person, EventType::Death, $research->death, $planned->deathRepoId, $planned->deathPlace, $written);
            $deaths++;
        }

        if ($research->burial !== null && $planned->burialRepoId !== null) {
            $this->createEvent($tree, $person, EventType::Burial, $research->burial, $planned->burialRepoId, $planned->burialPlace, $written);
            $burials++;
        }

        return $person;
    }

    /**
     * Replace the surname variants of a person with the list, in its order.
     */
    private function replaceSurnameVariants(Person $person, SurnameVariants $variants): void
    {
        $person->surnameVariants()->delete();

        foreach ($variants->names as $position => $name) {
            $person->surnameVariants()->create([
                'tree_id' => $person->tree_id,
                'name' => $name,
                'position' => $position,
            ]);
        }

        $person->unsetRelation('surnameVariants');
    }

    /**
     * Write the approved changes, the fills and the conflicts settled for the repo into a person an earlier import took,
     * and mark, create or change its birth, death and burial.
     *
     * @return array{fills: int, taken: int, births: int, deaths: int, burials: int}
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    private function writeUpdate(Tree $tree, Person $person, PlannedUpdate $update, ChangeApprovals $approvals, ConflictResolutions $resolutions, WrittenPlaces $written): array
    {
        /** @var list<array{0: string, 1: string|Gender|ResearchDate|PlannedPlace|SurnameVariants|SourceLinks|SourceKind|SourceRecordType|ConfidenceLevel|null}> $values */
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
        $places = [];
        $levels = [];
        $variants = null;

        foreach ($values as [$field, $value]) {
            match (true) {
                $field === 'surname_variants' && ($value === null || $value instanceof SurnameVariants) => $variants = $value ?? SurnameVariants::empty(),
                in_array($field, ['first_name', 'last_name'], true) && ($value === null || is_string($value)) => $attributes[$field] = $value,
                $field === 'gender' && ($value === null || $value instanceof Gender) => $attributes[$field] = $value,
                $field === 'note' && ($value === null || is_string($value)) => $attributes[$field] = NoteBlock::replace($person->note, $update->person->id, $value),
                in_array($field, ['birth_date', 'death_date', 'burial_date'], true) && ($value === null || $value instanceof ResearchDate) => $dates[$field] = $value,
                in_array($field, ['birth_place', 'death_place', 'burial_place'], true) && ($value === null || $value instanceof PlannedPlace) => $places[$field] = $value,
                in_array($field, ['birth_confidence', 'death_confidence', 'burial_confidence'], true) && ($value === null || $value instanceof ConfidenceLevel) => $levels[$field] = $value,
                in_array($field, ['birth_sources', 'death_sources'], true) && ($value === null || $value instanceof SourceLinks) => null,
                default => throw new RuntimeException("Plan ma niewłaściwą wartość pola {$field} dla osoby #{$person->id}."),
            };
        }

        if (array_key_exists('gender', $attributes)) {
            $this->guardParentSexChange($person, $attributes['gender']);
        }

        if ($attributes !== []) {
            $person->update($attributes);
        }

        if ($variants !== null) {
            $this->replaceSurnameVariants($person, $variants);
        }

        $counts = ['births' => 0, 'deaths' => 0, 'burials' => 0];
        $research = $update->person;

        foreach ([
            [EventType::Birth, $research->birth, RepoKeys::birth($research->id), $update->birthRepoId, $update->createBirth, 'birth_date', 'birth_place', $update->birthPlace, 'births', 'birth_confidence'],
            [EventType::Death, $research->death, RepoKeys::death($research->id), $update->deathRepoId, $update->createDeath, 'death_date', 'death_place', $update->deathPlace, 'deaths', 'death_confidence'],
            [EventType::Burial, $research->burial, RepoKeys::burial($research->id), $update->burialRepoId, $update->createBurial, 'burial_date', 'burial_place', $update->burialPlace, 'burials', 'burial_confidence'],
        ] as [$type, $repoEvent, $key, $markKey, $create, $dateField, $placeField, $createPlace, $counter, $levelField]) {
            if ($create) {
                if ($person->events->contains(fn (Event $event): bool => $event->type === $type)) {
                    throw new RuntimeException("Osoba {$person->displayName()} (#{$person->id}) dostała wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                if ($repoEvent !== null) {
                    $this->createEvent($tree, $person, $type, $repoEvent, $markKey, $createPlace, $written);
                    $counts[$counter]++;
                }

                continue;
            }

            $placeAttributes = array_key_exists($placeField, $places)
                ? $this->placeAttributes($tree, $type === EventType::Burial, $places[$placeField], $places[$placeField] === null, $written)
                : [];

            if ($markKey !== null) {
                $event = $person->events->first(fn (Event $each): bool => $each->type === $type && $each->repo_id === null);

                if ($event === null) {
                    throw new RuntimeException("Osobie {$person->displayName()} (#{$person->id}) zniknęło wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                $this->markEvent($event, $markKey, $dates[$dateField] ?? null, false, $placeAttributes, array_key_exists($levelField, $levels), $levels[$levelField] ?? null);

                continue;
            }

            if (array_key_exists($dateField, $dates) || $placeAttributes !== [] || array_key_exists($levelField, $levels)) {
                $event = $person->events->first(fn (Event $each): bool => $each->type === $type && $each->repo_id === $key);

                if ($event === null) {
                    throw new RuntimeException("Osobie {$person->displayName()} (#{$person->id}) zniknęło wydarzenie po planowaniu; nic nie zostało zapisane.");
                }

                $this->markEvent($event, $key, $dates[$dateField] ?? null, array_key_exists($dateField, $dates) && $dates[$dateField] === null, $placeAttributes, array_key_exists($levelField, $levels), $levels[$levelField] ?? null);
            }
        }

        return ['fills' => $fills, 'taken' => $taken, ...$counts];
    }

    /**
     * Write the repo id, the approved values and the note block into a matched person and mark its existing birth, death
     * and burial.
     *
     * @return array{fills: int, taken: int} the values the repo added to empty fields and the conflicts settled for the repo
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    private function writeMatch(Tree $tree, Person $person, PlannedMatch $match, ConflictResolutions $resolutions, WrittenPlaces $written): array
    {
        /** @var list<array{0: string, 1: string|Gender|ResearchDate|PlannedPlace|SurnameVariants|SourceLinks|SourceKind|SourceRecordType|ConfidenceLevel|null, 2: bool}> $changes */
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
        $places = [];
        $levels = [];
        $variants = null;
        $fills = 0;
        $taken = 0;

        foreach ($changes as [$field, $value, $fromRepo]) {
            match (true) {
                $field === 'surname_variants' && ($value === null || $value instanceof SurnameVariants) => $variants = $value ?? SurnameVariants::empty(),
                in_array($field, ['first_name', 'last_name'], true) && is_string($value) => $attributes[$field] = $value,
                $field === 'gender' && $value instanceof Gender => $attributes[$field] = $value,
                in_array($field, ['birth_date', 'death_date', 'burial_date'], true) && $value instanceof ResearchDate => $dates[$field] = $value,
                in_array($field, ['birth_place', 'death_place', 'burial_place'], true) && ($value === null || $value instanceof PlannedPlace) => $places[$field] = $value,
                in_array($field, ['birth_confidence', 'death_confidence', 'burial_confidence'], true) && ($value === null || $value instanceof ConfidenceLevel) => $levels[$field] = $value,
                in_array($field, ['birth_sources', 'death_sources'], true) && ($value === null || $value instanceof SourceLinks) => null,
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

        if ($variants !== null) {
            $this->replaceSurnameVariants($person, $variants);
        }

        foreach ([
            [EventType::Birth, $match->birthRepoId, $match->createBirth, 'birth_date', 'birth_place', 'birth_confidence'],
            [EventType::Death, $match->deathRepoId, $match->createDeath, 'death_date', 'death_place', 'death_confidence'],
            [EventType::Burial, $match->burialRepoId, $match->createBurial, 'burial_date', 'burial_place', 'burial_confidence'],
        ] as [$type, $repoId, $create, $dateField, $placeField, $levelField]) {
            $event = $person->events->first(fn (Event $each): bool => $each->type === $type);

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

            $placeAttributes = array_key_exists($placeField, $places)
                ? $this->placeAttributes($tree, $type === EventType::Burial, $places[$placeField], $places[$placeField] === null, $written)
                : [];

            $this->markEvent($event, $repoId, $dates[$dateField] ?? null, false, $placeAttributes, array_key_exists($levelField, $levels), $levels[$levelField] ?? null);
        }

        return ['fills' => $fills, 'taken' => $taken];
    }

    /**
     * Check that a field of the locked person still holds what the plan saw: empty for a fill, the value shown to the
     * author for a change or a conflict settled for the repo. A place is read as the plan showed it: the name of the
     * locality, the label of the cemetery for a burial.
     *
     * @param  string|null  $planned  the label the plan recorded, `null` or "—" for an empty field
     * @param  array<string, true>  $skip  ids of the sources whose links the plan did not take into account
     *
     * @throws RuntimeException
     */
    private function guardPerson(Tree $tree, Person $person, string $field, ?string $planned, array $skip = []): void
    {
        $planned = $planned === PlanImport::EMPTY_LABEL ? null : $planned;
        $current = match ($field) {
            'first_name' => PlanImport::cleanText($person->first_name),
            'last_name' => PlanImport::cleanText($person->last_name),
            'gender' => $person->gender?->label(),
            'surname_variants' => SurnameVariants::fromPerson($person)->label(),
            'note' => $person->repo_id === null ? null : NoteBlock::find($person->note, $person->repo_id),
            'birth_date' => ResearchDate::fromEvent($person->birth())?->label(),
            'death_date' => ResearchDate::fromEvent($person->death())?->label(),
            'burial_date' => ResearchDate::fromEvent($person->burial())?->label(),
            'birth_place' => $this->localityName($tree, $person->birth()?->locality_id),
            'death_place' => $this->localityName($tree, $person->death()?->locality_id),
            'burial_place' => $this->cemeteryLabel($tree, $person->burial()?->cemetery_id),
            'birth_confidence' => $person->birth()?->confidence?->value,
            'death_confidence' => $person->death()?->confidence?->value,
            'burial_confidence' => $person->burial()?->confidence?->value,
            'birth_sources' => $this->sourceLinksLabel($person->birth(), $skip),
            'death_sources' => $this->sourceLinksLabel($person->death(), $skip),
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
     * Check that the place of an existing wedding is still the one the plan saw.
     *
     * @param  string|null  $planned  the label the plan recorded, `null` for a wedding without a place
     *
     * @throws RuntimeException
     */
    private function guardWeddingPlace(Tree $tree, Event $event, string $repoId, ?string $planned): void
    {
        $planned = $planned === PlanImport::EMPTY_LABEL ? null : $planned;

        if ($this->localityName($tree, $event->locality_id) !== $planned) {
            throw new RuntimeException("Małżeństwo {$repoId} zmieniło się po planowaniu; nic nie zostało zapisane.");
        }
    }

    /**
     * Check that the confidence level of an existing wedding is still the one the plan saw.
     *
     * @param  string|null  $planned  the letter the plan recorded, `null` for a wedding without a level
     *
     * @throws RuntimeException
     */
    private function guardWeddingConfidence(Event $event, string $repoId, ?string $planned): void
    {
        $planned = $planned === PlanImport::EMPTY_LABEL ? null : $planned;

        if ($event->confidence?->value !== $planned) {
            throw new RuntimeException("Małżeństwo {$repoId} zmieniło się po planowaniu; nic nie zostało zapisane.");
        }
    }

    /**
     * Check that the links of an existing wedding to sources of the repo are still the ones the plan saw.
     *
     * @param  string|null  $planned  the label the plan recorded, `null` for a wedding without links
     * @param  array<string, true>  $skip  ids of the sources whose links the plan did not take into account
     *
     * @throws RuntimeException
     */
    private function guardWeddingSources(Event $event, string $repoId, ?string $planned, array $skip): void
    {
        $planned = $planned === PlanImport::EMPTY_LABEL ? null : $planned;

        if ($this->sourceLinksLabel($event, $skip) !== $planned) {
            throw new RuntimeException("Małżeństwo {$repoId} zmieniło się po planowaniu; nic nie zostało zapisane.");
        }
    }

    /**
     * The links of an event to sources that carry a repo id, as the plan shows them to the author, `null` for none.
     *
     * @param  array<string, true>  $skip  ids of the sources whose links the plan did not take into account
     */
    private function sourceLinksLabel(?Event $event, array $skip): ?string
    {
        if ($event === null) {
            return null;
        }

        $links = [];

        foreach ($event->sources()->whereNotNull('sources.repo_id')->get() as $source) {
            if (! isset($skip[(string) $source->repo_id])) {
                $links[] = new ResearchSourceLink((string) $source->repo_id, $source->getRelation('pivot')->entry_number);
            }
        }

        return (new SourceLinks($links))->label();
    }

    /**
     * The ids of the sources whose links the plan left out of the three values: the ones the reading skipped and the ones
     * the repo dropped, deleted or detached by this write.
     *
     * @return array<string, true>
     */
    private function unlinkedSources(ImportPlan $plan): array
    {
        $ids = $plan->skippedSources;

        foreach ($plan->removals as $removal) {
            if ($removal->kind === RepoKeyKind::Source) {
                $ids[$removal->key] = true;
            }
        }

        return $ids;
    }

    private function localityName(Tree $tree, ?int $id): ?string
    {
        return $id === null ? null : $tree->localities()->whereKey($id)->value('name');
    }

    private function cemeteryLabel(Tree $tree, ?int $id): ?string
    {
        return $id === null ? null : $tree->cemeteries()->with('locality')->whereKey($id)->first()?->label();
    }

    /**
     * Every place the plan names, to check or to add. Without the answers it is all of them; with them only the places an
     * item uses that the write takes: new and restored persons, the events the write creates, the fills, the approved
     * changes, the conflicts settled for the repo, and a new cemetery counts as a use of its locality.
     *
     * @return list<PlannedPlace>
     */
    private function collectPlaces(ImportPlan $plan, ?ChangeApprovals $approvals = null, ?ConflictResolutions $resolutions = null): array
    {
        $changeTaken = fn (PlannedChange $change): bool => $approvals === null || ! $approvals->rejects($change->key);
        $conflictTaken = fn (ValueConflict $conflict): bool => $resolutions === null || $resolutions->takesRepo($conflict->key);
        $places = [];

        $persons = [...$plan->persons];

        foreach ($plan->restorations as $restoration) {
            if ($restoration->person !== null) {
                $persons[] = $restoration->person;
            }

            $places[] = $restoration->place;
        }

        foreach ($persons as $planned) {
            array_push($places, $planned->birthPlace, $planned->deathPlace, $planned->burialRepoId === null ? null : $planned->burialPlace);
        }

        foreach ([...$plan->matched, ...$plan->updates] as $planned) {
            array_push(
                $places,
                $planned->createBirth ? $planned->birthPlace : null,
                $planned->createDeath ? $planned->deathPlace : null,
                $planned->createBurial ? $planned->burialPlace : null,
            );

            foreach ($planned->fills as $fill) {
                $places[] = $fill->value instanceof PlannedPlace ? $fill->value : null;
            }

            foreach ($planned->conflicts as $conflict) {
                $places[] = $conflictTaken($conflict) && $conflict->repoValue instanceof PlannedPlace ? $conflict->repoValue : null;
            }
        }

        foreach ($plan->updates as $update) {
            foreach ($update->changes as $change) {
                $places[] = $changeTaken($change) && $change->repoValue instanceof PlannedPlace ? $change->repoValue : null;
            }
        }

        foreach ($plan->marriages as $planned) {
            if ($planned->existingEventId === null) {
                $places[] = $planned->place;

                continue;
            }

            $places[] = $planned->placeFill?->value instanceof PlannedPlace ? $planned->placeFill->value : null;
            $places[] = $planned->placeConflict !== null && $conflictTaken($planned->placeConflict) && $planned->placeConflict->repoValue instanceof PlannedPlace ? $planned->placeConflict->repoValue : null;
            $places[] = $planned->placeChange !== null && $changeTaken($planned->placeChange) && $planned->placeChange->repoValue instanceof PlannedPlace ? $planned->placeChange->repoValue : null;
        }

        array_push($places, ...$this->collectSourcePlaces($plan, $changeTaken, $conflictTaken));

        return array_values(array_filter($places));
    }

    /**
     * The parishes the sources of the plan name: new and restored sources, the fills of joined and known sources, and the
     * changes and conflicts of known sources that the write takes.
     *
     * @param  callable(PlannedChange): bool  $changeTaken
     * @param  callable(ValueConflict): bool  $conflictTaken
     * @return list<PlannedPlace|null>
     */
    private function collectSourcePlaces(ImportPlan $plan, callable $changeTaken, callable $conflictTaken): array
    {
        $places = [];

        foreach ($plan->newSources as $planned) {
            $places[] = $planned->source->kind === SourceKind::MetricalRecord ? $planned->parish : null;
        }

        foreach ($plan->restorations as $restoration) {
            $places[] = $restoration->source !== null && $restoration->source->source->kind === SourceKind::MetricalRecord ? $restoration->source->parish : null;
        }

        $fills = array_merge(...array_map(fn ($merge): array => $merge->fills, $plan->mergedSources), ...array_map(fn ($update): array => $update->fills, $plan->sourceUpdates));
        $values = array_map(fn ($fill): mixed => $fill->field === 'parish' ? $fill->value : null, $fills);

        foreach ($plan->sourceUpdates as $update) {
            foreach ($update->changes as $change) {
                $values[] = $change->field === 'parish' && $changeTaken($change) ? $change->repoValue : null;
            }

            foreach ($update->conflicts as $conflict) {
                $values[] = $conflict->field === 'parish' && $conflictTaken($conflict) ? $conflict->repoValue : null;
            }
        }

        foreach ($values as $value) {
            $places[] = $value instanceof PlannedPlace ? $value : null;
        }

        return $places;
    }

    /**
     * Check that the places the plan names are still as the plan saw them: every locality and cemetery it points at is in
     * the tree, no locality or variant of the tree has taken the name of a new locality (the unique index compares
     * names with their diacritics, so it would miss "Kołemczyce" next to "Kolemczyce"), and no variant to remember
     * belongs to another locality or is the name of one.
     *
     * @throws RuntimeException
     */
    private function guardPlaces(Tree $tree, ImportPlan $plan): void
    {
        /** @var array<int, string> $localities */
        $localities = [];
        /** @var array<int, string> $cemeteries */
        $cemeteries = [];
        /** @var array<int, string> $parishes */
        $parishes = [];

        foreach ($this->collectPlaces($plan) as $place) {
            if ($place->parishId !== null) {
                $parishes[$place->parishId] = $place->label;
            }

            if ($place->localityId !== null) {
                $localities[$place->localityId] = $place->label;
            }

            if ($place->cemeteryId !== null) {
                $cemeteries[$place->cemeteryId] = $place->label;
            }
        }

        foreach ($plan->newCemeteries as $cemetery) {
            if ($cemetery->localityId !== null) {
                $localities[$cemetery->localityId] = $cemetery->name ?? 'cmentarz';
            }
        }

        foreach ($plan->newParishes as $parish) {
            if ($parish->localityId !== null) {
                $localities[$parish->localityId] = $parish->dedication ?? 'parafia';
            }
        }

        foreach ($plan->aliases as $alias) {
            $localities[$alias->localityId] = $alias->name;
        }

        foreach ([[$tree->localities(), $localities], [$tree->cemeteries(), $cemeteries], [$tree->parishes(), $parishes]] as [$query, $planned]) {
            if ($planned === []) {
                continue;
            }

            $found = $query->whereIn('id', array_keys($planned))->pluck('id')->all();

            foreach ($planned as $id => $label) {
                if (! in_array($id, $found, true)) {
                    throw new RuntimeException("Miejsce {$label} zmieniło się po planowaniu; nic nie zostało zapisane.");
                }
            }
        }

        if ($plan->newLocalities === [] && $plan->aliases === []) {
            return;
        }

        /** @var array<string, array<int, true>> $holders key of a name => ids of the localities whose name or variant has it */
        $holders = [];

        foreach ($tree->localities()->get(['id', 'name']) as $locality) {
            $holders[(string) PlaceName::key($locality->name)][$locality->id] = true;
        }

        foreach ($tree->localityAliases()->get(['locality_id', 'name']) as $variant) {
            $holders[(string) PlaceName::key($variant->name)][$variant->locality_id] = true;
        }

        foreach ($plan->newLocalities as $name) {
            if (isset($holders[(string) PlaceName::key($name)])) {
                throw new RuntimeException("Miejsce {$name} zmieniło się po planowaniu; nic nie zostało zapisane.");
            }
        }

        foreach ($plan->aliases as $alias) {
            $seen = array_fill_keys([...$alias->holders, $alias->localityId], true);

            if (array_diff_key($holders[(string) PlaceName::key($alias->name)] ?? [], $seen) !== []) {
                throw new RuntimeException("Miejsce {$alias->name} zmieniło się po planowaniu; nic nie zostało zapisane.");
            }
        }
    }

    /**
     * Add to the gazetteer the new localities and cemeteries, and to the register the new parishes, that an item of the write uses, and remember the variants the
     * author chose. A variant the tree already holds (letter case ignored, as its unique index does) is not added twice;
     * one that only repeats the locality's name is still added when another locality shares its key, so that the next
     * import recognizes it.
     *
     * @return array{0: WrittenPlaces, 1: int} the ids of the places and parishes added and the number of variants saved
     *
     * @throws RuntimeException when a rule of the app stops a place
     */
    private function writePlaces(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): array
    {
        $usedLocalities = [];
        $usedCemeteries = [];
        $usedParishes = [];

        foreach ($this->collectPlaces($plan, $approvals, $resolutions) as $place) {
            if ($place->newLocality !== null) {
                $usedLocalities[$place->newLocality] = true;
            }

            if ($place->newParish !== null) {
                $usedParishes[$place->newParish] = true;
            }

            if ($place->newCemetery !== null) {
                $usedCemeteries[$place->newCemetery] = true;
            }
        }

        foreach (array_keys($usedCemeteries) as $key) {
            $cemetery = $plan->newCemeteries[$key] ?? throw new RuntimeException("Plan odwołuje się do cmentarza {$key}, którego nie ma w planie.");

            if ($cemetery->newLocality !== null) {
                $usedLocalities[$cemetery->newLocality] = true;
            }
        }

        foreach (array_keys($usedParishes) as $key) {
            $parish = $plan->newParishes[$key] ?? throw new RuntimeException("Plan odwołuje się do parafii {$key}, której nie ma w planie.");

            if ($parish->newLocality !== null) {
                $usedLocalities[$parish->newLocality] = true;
            }
        }

        $localities = [];

        foreach ($plan->newLocalities as $key => $name) {
            if (! isset($usedLocalities[$key])) {
                continue;
            }

            try {
                $localities[$key] = $this->storeLocality->handle($tree, [
                    'name' => $name,
                    'description' => null,
                    'kind' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'historical_affiliation' => null,
                    'is_defunct' => false,
                    'defunct_note' => null,
                ])->id;
            } catch (ValidationException $exception) {
                throw $this->withContext("miejscowość {$name}", $exception);
            }
        }

        $aliases = 0;
        $variants = [];

        foreach ($tree->localityAliases()->get(['name']) as $variant) {
            $variants[mb_strtolower($variant->name)] = true;
        }

        foreach ($plan->aliases as $alias) {
            $name = mb_strtolower($alias->name);

            if (isset($variants[$name])) {
                continue;
            }

            $tree->localityAliases()->create(['locality_id' => $alias->localityId, 'name' => $alias->name]);
            $variants[$name] = true;
            $aliases++;
        }

        $cemeteries = [];

        foreach ($plan->newCemeteries as $key => $cemetery) {
            if (! isset($usedCemeteries[$key])) {
                continue;
            }

            $localityId = $cemetery->localityId ?? ($localities[$cemetery->newLocality ?? ''] ?? null)
                ?? throw new RuntimeException("Cmentarz {$key} nie ma miejscowości w planie.");

            try {
                $cemeteries[$key] = $this->storeCemetery->handle($tree, [
                    'name' => $cemetery->name,
                    'locality_id' => $localityId,
                    'manager' => null,
                    'religion' => null,
                    'description' => null,
                ])->id;
            } catch (ValidationException $exception) {
                throw $this->withContext('cmentarz '.($cemetery->name ?? $key), $exception);
            }
        }

        $parishes = [];

        foreach ($plan->newParishes as $key => $parish) {
            if (! isset($usedParishes[$key])) {
                continue;
            }

            $localityId = $parish->localityId ?? ($localities[$parish->newLocality ?? ''] ?? null)
                ?? throw new RuntimeException("Parafia {$key} nie ma miejscowości w planie.");

            try {
                $parishes[$key] = $this->storeParish->handle($tree, [
                    'locality_id' => $localityId,
                    'kind' => $parish->kind,
                    'religion' => $parish->religion,
                    'dedication' => $parish->dedication,
                ])->id;
            } catch (ValidationException $exception) {
                throw $this->withContext('parafia '.($parish->dedication ?? $key), $exception);
            }
        }

        return [new WrittenPlaces($localities, $cemeteries, $parishes), $aliases];
    }

    /**
     * The columns that put an event at a place, from a place of the plan. A burial gets its cemetery and the locality of
     * that cemetery together, from {@see MapsEventInput::burialPlaceFrom()}. With no place and `$clear` the columns are
     * emptied (both for a burial), with neither nothing changes.
     *
     * @return array{locality_id?: int|null, cemetery_id?: int|null}
     *
     * @throws RuntimeException when the plan names a place the write did not add or the tree does not hold
     */
    private function placeAttributes(Tree $tree, bool $burial, ?PlannedPlace $place, bool $clear, WrittenPlaces $written): array
    {
        if ($place === null) {
            if (! $clear) {
                return [];
            }

            return $burial ? ['locality_id' => null, 'cemetery_id' => null] : ['locality_id' => null];
        }

        if ($burial) {
            $cemeteryId = $written->cemeteryId($place);

            $columns = $cemeteryId === null ? null : $this->burialPlaceFrom($tree, ['cemetery_id' => $cemeteryId]);

            return ($columns === null ? null : Arr::only($columns, ['locality_id', 'cemetery_id']))
                ?? throw new RuntimeException("Cmentarz {$place->label} nie należy do drzewa; nic nie zostało zapisane.");
        }

        return ['locality_id' => $written->localityId($place) ?? throw new RuntimeException("Miejscowość {$place->label} nie należy do drzewa; nic nie zostało zapisane.")];
    }

    /**
     * A failed rule of the app as a rollback message that says which record of the plan it came from.
     */
    private function withContext(string $context, ValidationException $exception): RuntimeException
    {
        return new RuntimeException($context.': '.implode(' ', Arr::flatten($exception->errors())), 0, $exception);
    }

    /**
     * Give an existing event its repo id and, when given, its date, or empty its date when the repo cleared it. The place
     * changes only as the plan says, through `$place` (the columns of {@see placeAttributes()}); the wedding form and the
     * linked sources stay. The confidence level changes only with `$setConfidence`, to `$confidence` or to none. Saved
     * through the model so that the derived flag follows.
     *
     * @param  array{locality_id?: int|null, cemetery_id?: int|null}  $place
     */
    private function markEvent(Event $event, string $repoId, ?ResearchDate $date, bool $clear = false, array $place = [], bool $setConfidence = false, ?ConfidenceLevel $confidence = null): void
    {
        $attributes = ['repo_id' => $repoId, ...$place];

        if ($setConfidence) {
            $attributes['confidence'] = $confidence;
        }

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

    /**
     * Create an event of the person with its date and place. A burial exists only with a cemetery, so one without a place
     * stops the write.
     *
     * @throws RuntimeException
     */
    private function createEvent(Tree $tree, Person $person, EventType $type, ResearchEvent $research, ?string $repoId, ?PlannedPlace $place, WrittenPlaces $written): void
    {
        $burial = $type === EventType::Burial;

        if ($burial && $place === null) {
            throw new RuntimeException("Pochowanie {$repoId} nie ma cmentarza w planie; nic nie zostało zapisane.");
        }

        $event = $tree->events()->create([
            'type' => $type,
            ...$this->eventAttributesFrom([...$this->dateInput($research->date), 'locality_id' => $place === null || $burial ? null : $written->localityId($place), 'confidence' => $research->level]),
            ...($place === null ? [] : $this->placeAttributes($tree, $burial, $place, false, $written)),
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
