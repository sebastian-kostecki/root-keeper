<?php

namespace App\Actions\Persons;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Http\Resources\PersonFormResource;
use App\Http\Resources\PersonResource;
use App\Http\Resources\TimelineEntryResource;
use App\Models\Person;
use App\Models\User;
use App\Support\PersonTimeline;

class BuildPersonPreview
{
    /**
     * Build the payload rendered by the person preview (modal or page).
     *
     * @return array{
     *     person: array<string, mixed>,
     *     personForm: array<string, mixed>,
     *     genders: array<int, array{value: string, label: string}>,
     *     religions: array<int, array{value: string, label: string}>,
     *     isRootPerson: bool,
     * }
     */
    public function handle(Person $person, User $user): array
    {
        $person->load(['events.sources', 'events.locality', 'parents.events', 'children.events.locality', 'marriages.sources', 'marriages.locality', 'marriages.participants.events.locality', 'residences.locality', 'gravestones.cemetery.locality']);

        return [
            'person' => [
                ...PersonResource::make($person)->resolve(),
                'note' => $person->note,
                'timeline' => TimelineEntryResource::collection(PersonTimeline::for($person))->resolve(),
            ],
            'personForm' => PersonFormResource::make($person)->resolve(),
            'genders' => Gender::options(),
            'religions' => Religion::options(),
            'isRootPerson' => $user->tree->root_person_id === $person->id,
        ];
    }
}
