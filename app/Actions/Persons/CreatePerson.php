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
    /**
     * Create a person in the given tree, along with its birth event and,
     * when the person is deceased, its death event.
     *
     * @param  array{
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     residence?: string|null,
     *     religion?: string|null,
     *     is_deceased?: bool|string|null,
     *     birth?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         is_approximate?: bool|string|null,
     *         place?: string|null,
     *     },
     *     death?: array{
     *         year?: int|string|null,
     *         month?: int|string|null,
     *         day?: int|string|null,
     *         is_approximate?: bool|string|null,
     *         place?: string|null,
     *     },
     * }  $data
     */
    public function handle(Tree $tree, array $data): Person
    {
        return DB::transaction(function () use ($tree, $data): Person {
            $person = $tree->people()->create([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'residence' => $data['residence'] ?? null,
                'religion' => $data['religion'] ?? null,
            ]);

            $this->createEvent($tree, $person, EventType::Birth, $data['birth'] ?? []);

            if ($this->isChecked($data['is_deceased'] ?? null)) {
                $this->createEvent($tree, $person, EventType::Death, $data['death'] ?? []);
            }

            return $person;
        });
    }

    /**
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|string|null, place?: string|null}  $date
     */
    private function createEvent(Tree $tree, Person $person, EventType $type, array $date): void
    {
        $event = $tree->events()->create([
            'type' => $type,
            'date_year' => $date['year'] ?? null,
            'date_month' => $date['month'] ?? null,
            'date_day' => $date['day'] ?? null,
            'date_is_approximate' => $this->isChecked($date['is_approximate'] ?? null),
            'place' => $date['place'] ?? null,
        ]);

        $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    }

    private function isChecked(bool|string|null $value): bool
    {
        return $value === true || $value === '1';
    }
}
