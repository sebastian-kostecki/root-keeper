<?php

namespace App\Actions\Persons;

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

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
     *     residence?: string|null,
     *     religion?: string|null,
     *     is_deceased?: bool|int|string|null,
     *     birth?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         is_approximate?: bool|int|string|null,
     *         place?: string|null,
     *     },
     *     death?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         is_approximate?: bool|int|string|null,
     *         place?: string|null,
     *     },
     * }  $data
     */
    public function handle(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data): Person {
            $person->load('events');

            $person->update([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'gender' => $data['gender'] ?? null,
                'residence' => $data['residence'] ?? null,
                'religion' => $data['religion'] ?? null,
            ]);

            $this->putEvent($person, EventType::Birth, $data['birth'] ?? []);

            if ($this->isChecked($data['is_deceased'] ?? null)) {
                $this->putEvent($person, EventType::Death, $data['death'] ?? []);
            } else {
                $person->death()?->delete();
            }

            return $person;
        });
    }

    /**
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, place?: string|null}  $date
     */
    private function putEvent(Person $person, EventType $type, array $date): void
    {
        $attributes = $this->eventAttributesFrom($date);

        $event = $type === EventType::Birth ? $person->birth() : $person->death();

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
