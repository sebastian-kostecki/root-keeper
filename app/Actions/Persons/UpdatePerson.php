<?php

namespace App\Actions\Persons;

use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParticipantRole;
use App\Models\Person;
use App\Support\ParentChildGraph;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePerson
{
    use MapsEventInput;

    /**
     * Apply validated form data to an existing person, updating its birth
     * and death events in place so linked sources are never dropped.
     *
     * @param  array{
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     maiden_name?: string|null,
     *     gender?: string|null,
     *     residence?: string|null,
     *     religion?: string|null,
     *     is_deceased?: bool|int|string|null,
     *     birth?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         is_approximate?: bool|int|string|null,
     *         locality_id?: int|string|null,
     *     },
     *     death?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         is_approximate?: bool|int|string|null,
     *         locality_id?: int|string|null,
     *     },
     *     burial?: array{locality_id?: int|string|null},
     * }  $data
     */
    public function handle(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data): Person {
            $person->load('events');

            $this->guardParentSexChange($person, $data['gender'] ?? null);

            $person->update([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'maiden_name' => $data['maiden_name'] ?? null,
                'gender' => $data['gender'] ?? null,
                'residence' => $data['residence'] ?? null,
                'religion' => $data['religion'] ?? null,
            ]);

            $this->putEvent($person, EventType::Birth, $data['birth'] ?? []);

            if ($this->isChecked($data['is_deceased'] ?? null)) {
                $this->putEvent($person, EventType::Death, $data['death'] ?? []);

                // Full replace, like birth and death: a payload without `burial` clears the burial place.
                $burialLocalityId = $data['burial']['locality_id'] ?? null;

                if (filled($burialLocalityId)) {
                    $this->putEvent($person, EventType::Burial, ['locality_id' => $burialLocalityId]);
                } else {
                    $person->burial()?->delete();
                }
            } else {
                $person->death()?->delete();
                $person->burial()?->delete();
            }

            return $person;
        });
    }

    /**
     * Changing the sex to a known one must not give any child of the person
     * a second parent of that sex. Clearing the sex or keeping it never
     * checks relations, so older inconsistent pairs do not block other edits.
     */
    private function guardParentSexChange(Person $person, ?string $newGender): void
    {
        $new = $newGender === null ? null : Gender::tryFrom($newGender);
        $stored = Person::query()->whereKey($person->id)->lockForUpdate()->firstOrFail()->gender;

        if ($new === null || $new === $stored) {
            return;
        }

        $graph = ParentChildGraph::forTree($person->tree);

        $otherParentIds = collect($graph->childrenOf($person->id))
            ->flatMap(fn (int $childId): array => $graph->parentsOf($childId))
            ->reject(fn (int $parentId): bool => $parentId === $person->id)
            ->unique()
            ->all();

        $conflicts = $person->tree->people()
            ->whereIn('id', $otherParentIds)
            ->where('gender', $new)
            ->exists();

        if ($conflicts) {
            throw ValidationException::withMessages([
                'gender' => $new === Gender::Male
                    ? 'Ta osoba jest rodzicem dziecka, które ma już ojca.'
                    : 'Ta osoba jest rodzicem dziecka, które ma już matkę.',
            ]);
        }
    }

    /**
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, locality_id?: int|string|null}  $date
     */
    private function putEvent(Person $person, EventType $type, array $date): void
    {
        $attributes = $this->eventAttributesFrom($date);

        $event = match ($type) {
            EventType::Birth => $person->birth(),
            EventType::Death => $person->death(),
            EventType::Burial => $person->burial(),
            EventType::Marriage => null,
        };

        if ($event !== null) {
            $event->update($attributes);

            return;
        }

        $event = $person->tree->events()->create([
            'type' => $type,
            ...$attributes,
        ]);

        $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    }
}
