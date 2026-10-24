<?php

namespace App\Actions\Import;

use App\Actions\Persons\GuardsParentSexChange;
use App\Actions\Persons\MapsEventInput;
use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\PlannedMatch;
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
    ) {}

    /**
     * Write an import plan into the tree, all or nothing. Any exception rolls the whole write back.
     *
     * Matched persons and the pairs and weddings already in the tree get their repo ids and the approved values; nothing
     * in them is removed, and what the import does not name (photo, residences, sources, places) stays as it is.
     *
     * @throws RuntimeException when the plan is blocked, the tree got a person from an import after planning, a matched
     *                          person or an existing wedding changed since planning, or a step breaks a rule of the app
     * @throws ValidationException when a new wedding breaks a rule of the app
     */
    public function handle(Tree $tree, ImportPlan $plan, ConflictResolutions $resolutions): ImportResult
    {
        if ($plan->blockedReason !== null) {
            throw new RuntimeException($plan->blockedReason);
        }

        return DB::transaction(function () use ($tree, $plan, $resolutions): ImportResult {
            Tree::whereKey($tree->id)->lockForUpdate()->firstOrFail();

            if ($tree->people()->whereNotNull('repo_id')->exists()) {
                throw new RuntimeException(PlanImport::BLOCKED_REASON);
            }

            $matchedPeople = $this->lockMatchedPeople($tree, $plan);

            $people = [];
            $births = 0;
            $deaths = 0;
            $fills = 0;
            $taken = 0;

            foreach ($plan->matched as $match) {
                $person = $matchedPeople[$match->personId];
                $people[$match->person->id] = $person;

                try {
                    $written = $this->writeMatch($person, $match, $resolutions);
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

            foreach ($plan->persons as $planned) {
                $research = $planned->person;

                $person = $tree->people()->create([
                    'first_name' => $research->givenNames,
                    'last_name' => $research->surname,
                    'gender' => $research->gender,
                    'note' => $planned->note,
                    'repo_id' => $research->id,
                ]);
                $people[$research->id] = $person;

                if ($research->birth !== null) {
                    $this->createEvent($tree, $person, EventType::Birth, $research->birth, $planned->birthRepoId);
                    $births++;
                }

                if ($research->death !== null) {
                    $this->createEvent($tree, $person, EventType::Death, $research->death, $planned->deathRepoId);
                    $deaths++;
                }
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

                    $date = $planned->dateFill?->value;
                    $fills += $planned->dateFill === null ? 0 : 1;

                    if ($planned->dateFill !== null) {
                        $this->guardWedding($event, $planned->repoId, $planned->dateFill->appLabel);
                    }

                    if ($planned->dateConflict !== null && $resolutions->takesRepo($planned->dateConflict->key)) {
                        $this->guardWedding($event, $planned->repoId, $planned->dateConflict->appLabel);
                        $date = $planned->dateConflict->repoValue;
                        $taken++;
                    }

                    $this->markEvent($event, $planned->repoId, $date instanceof ResearchDate ? $date : null);
                    $markedMarriages++;

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

            return new ImportResult(
                persons: count($plan->persons),
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
            );
        });
    }

    /**
     * Leave the memory of this import: the repo values of every key the plan wrote or marked, and no memory of the
     * keys it forgets. The query builder skips the casts of the model, so `values` goes in as encoded JSON.
     */
    private function rememberSnapshots(Tree $tree, ImportPlan $plan): void
    {
        if ($plan->forgottenSnapshots !== []) {
            $tree->repoSnapshots()->whereIn('repo_id', $plan->forgottenSnapshots)->delete();
        }

        $rows = [];

        foreach ($plan->snapshots as $key => $values) {
            $rows[] = [
                'repo_id' => $key,
                'values' => json_encode($values, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE),
                'deletion_kept' => false,
            ];
        }

        if ($rows !== []) {
            $tree->repoSnapshots()->upsert($rows, ['tree_id', 'repo_id'], ['values', 'deletion_kept']);
        }
    }

    /**
     * Lock the matched persons in id order and check that each still belongs to the tree and has not been imported.
     *
     * @return Collection<int, Person>
     *
     * @throws RuntimeException
     */
    private function lockMatchedPeople(Tree $tree, ImportPlan $plan): Collection
    {
        $ids = array_map(fn (PlannedMatch $match): int => $match->personId, $plan->matched);
        sort($ids);

        if (count(array_unique($ids)) !== count($ids)) {
            throw new RuntimeException('Plan dopasowuje jedną osobę drzewa do kilku osób z repo; nic nie zostało zapisane.');
        }

        $people = $tree->people()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->with('events')->get()->keyBy('id');

        foreach ($ids as $id) {
            $person = $people->get($id);

            if ($person === null) {
                throw new RuntimeException("Dopasowanej osoby #{$id} nie ma już w drzewie konta; nic nie zostało zapisane.");
            }

            if ($person->repo_id !== null) {
                throw new RuntimeException("Dopasowana osoba #{$id} ma już id z importu; nic nie zostało zapisane.");
            }

            $person->setRelation('tree', $tree);
        }

        return $people;
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
        /** @var list<array{0: string, 1: string|Gender|ResearchDate, 2: bool}> $changes */
        $changes = [];

        foreach ($match->fills as $fill) {
            $this->guardPerson($person, $fill->field, $fill->appLabel);
            $changes[] = [$fill->field, $fill->value, false];
        }

        foreach ($match->conflicts as $conflict) {
            if ($resolutions->takesRepo($conflict->key)) {
                $this->guardPerson($person, $conflict->field, $conflict->appLabel);
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
            $attributes['note'] = trim((string) $person->note) === '' ? $match->note : $person->note."\n\n".$match->note;
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
     * author for a conflict settled for the repo.
     *
     * @param  string|null  $planned  the label the plan recorded, `null` for an empty field
     *
     * @throws RuntimeException
     */
    private function guardPerson(Person $person, string $field, ?string $planned): void
    {
        $current = match ($field) {
            'first_name' => PlanImport::cleanText($person->first_name),
            'last_name' => PlanImport::cleanText($person->last_name),
            'gender' => $person->gender?->label(),
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
     * Give an existing event its repo id and, when given, its date. Only the date columns change: the place, the
     * cemetery, the wedding form and the linked sources stay. Saved through the model so that the derived flag follows.
     */
    private function markEvent(Event $event, string $repoId, ?ResearchDate $date): void
    {
        $attributes = ['repo_id' => $repoId];

        if ($date !== null) {
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
