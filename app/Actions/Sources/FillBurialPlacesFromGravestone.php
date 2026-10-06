<?php

namespace App\Actions\Sources;

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Enums\SourceKind;
use App\Models\Person;
use App\Models\Source;

class FillBurialPlacesFromGravestone
{
    /**
     * Give every deceased person listed on the gravestone whose burial place is empty the
     * gravestone's cemetery as burial place. Existing burial places, people without a death
     * and a gravestone without a cemetery are left alone.
     */
    public function handle(Source $gravestone): void
    {
        if ($gravestone->kind !== SourceKind::Gravestone || $gravestone->cemetery_locality_id === null) {
            return;
        }

        $gravestone->buriedPeople()->with('events')->get()->each(function (Person $person) use ($gravestone): void {
            if ($person->death() === null || $person->burial() !== null) {
                return;
            }

            $burial = $gravestone->tree->events()->create([
                'type' => EventType::Burial,
                'date_is_approximate' => false,
                'locality_id' => $gravestone->cemetery_locality_id,
            ]);

            $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        });
    }
}
