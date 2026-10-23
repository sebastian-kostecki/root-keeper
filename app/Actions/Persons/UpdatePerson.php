<?php

namespace App\Actions\Persons;

use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParticipantRole;
use App\Models\Person;
use App\Models\Residence;
use App\Support\ParentChildGraph;
use App\Support\PersonOrdering;
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
     *     gender?: string|null,
     *     religion?: string|null,
     *     is_deceased?: bool|int|string|null,
     *     birth?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         qualifier?: DateQualifier|string|null,
     *         until_year?: int|string|null,
     *         locality_id?: int|string|null,
     *     },
     *     death?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         qualifier?: DateQualifier|string|null,
     *         until_year?: int|string|null,
     *         locality_id?: int|string|null,
     *     },
     *     burial?: array{cemetery_id?: int|string|null},
     *     residences?: array<int, array{locality_id: int|string, year_from?: int|string|null, year_to?: int|string|null}>,
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
                'gender' => $data['gender'] ?? null,
                'religion' => $data['religion'] ?? null,
            ]);

            $this->putEvent($person, EventType::Birth, $data['birth'] ?? []);

            if ($this->isChecked($data['is_deceased'] ?? null)) {
                $this->putEvent($person, EventType::Death, $data['death'] ?? []);

                // Full replace, like birth and death: a payload without `burial` clears the burial place.
                $burialPlace = $this->burialPlaceFrom($person->tree, $data['burial'] ?? []);

                if ($burialPlace !== null) {
                    $this->putEvent($person, EventType::Burial, ['locality_id' => $burialPlace['locality_id']], ['cemetery_id' => $burialPlace['cemetery_id']]);
                } else {
                    $person->burial()?->delete();
                }
            } else {
                $person->death()?->delete();
                $person->burial()?->delete();
            }

            $this->replaceResidences($person, $data['residences'] ?? []);

            return $person;
        });
    }

    /**
     * Full replace of the residence list, in payload order so ids grow in view
     * order. An unchanged list is left alone so saving other sections never
     * rewrites residence rows.
     *
     * @param  array<int, array{locality_id: int|string, year_from?: int|string|null, year_to?: int|string|null}>  $rows
     */
    private function replaceResidences(Person $person, array $rows): void
    {
        $submitted = collect(array_values($rows))
            ->map(fn (array $row): array => [
                (int) $row['locality_id'],
                filled($row['year_from'] ?? null) ? (int) $row['year_from'] : null,
                filled($row['year_to'] ?? null) ? (int) $row['year_to'] : null,
            ])
            ->all();

        $current = PersonOrdering::residences($person->residences()->get())
            ->map(fn (Residence $residence): array => [$residence->locality_id, $residence->year_from, $residence->year_to])
            ->all();

        if ($submitted === $current) {
            return;
        }

        $person->residences()->delete();

        foreach ($submitted as [$localityId, $yearFrom, $yearTo]) {
            $person->residences()->create([
                'tree_id' => $person->tree_id,
                'locality_id' => $localityId,
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
            ]);
        }
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
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, qualifier?: DateQualifier|string|null, until_year?: int|string|null, locality_id?: int|string|null}  $date
     * @param  array{cemetery_id: int|string|null}|array{}  $extra
     */
    private function putEvent(Person $person, EventType $type, array $date, array $extra = []): void
    {
        $attributes = [...$this->eventAttributesFrom($date), ...$extra];

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
