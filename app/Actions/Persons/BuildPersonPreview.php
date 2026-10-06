<?php

namespace App\Actions\Persons;

use App\Enums\Gender;
use App\Enums\Religion;
use App\Http\Resources\PersonFormResource;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\User;

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
        $person->load(['events.sources', 'events.locality', 'parents.events', 'children.events', 'marriages.sources', 'marriages.locality', 'marriages.participants.events']);

        return [
            'person' => PersonResource::make($person)->resolve(),
            'personForm' => PersonFormResource::make($person)->resolve(),
            'genders' => Gender::options(),
            'religions' => Religion::options(),
            'isRootPerson' => $user->tree->root_person_id === $person->id,
        ];
    }
}
