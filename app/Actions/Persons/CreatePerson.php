<?php

namespace App\Actions\Persons;

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;

class CreatePerson
{
    use MapsEventInput;

    /**
     * Create a person in the given tree, along with its birth event and,
     * when the person is deceased, its death event and optional burial place.
     *
     * @param  array{
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     maiden_name?: string|null,
     *     gender?: string|null,
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
     *     burial?: array{cemetery_id?: int|string|null},
     * }  $data
     */
    public function handle(Tree $tree, array $data): Person
    {
        return DB::transaction(function () use ($tree, $data): Person {
            $person = $tree->people()->create([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'maiden_name' => $data['maiden_name'] ?? null,
                'gender' => $data['gender'] ?? null,
                'religion' => $data['religion'] ?? null,
            ]);

            $this->createEvent($tree, $person, EventType::Birth, $data['birth'] ?? []);

            if ($this->isChecked($data['is_deceased'] ?? null)) {
                $this->createEvent($tree, $person, EventType::Death, $data['death'] ?? []);

                $burialPlace = $this->burialPlaceFrom($tree, $data['burial'] ?? []);

                if ($burialPlace !== null) {
                    $this->createEvent($tree, $person, EventType::Burial, ['locality_id' => $burialPlace['locality_id']], ['cemetery_id' => $burialPlace['cemetery_id']]);
                }
            }

            return $person;
        });
    }

    /**
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, locality_id?: int|string|null}  $date
     * @param  array{cemetery_id: int|string|null}|array{}  $extra
     */
    private function createEvent(Tree $tree, Person $person, EventType $type, array $date, array $extra = []): void
    {
        $event = $tree->events()->create([
            'type' => $type,
            ...$this->eventAttributesFrom($date),
            ...$extra,
        ]);

        $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    }
}
