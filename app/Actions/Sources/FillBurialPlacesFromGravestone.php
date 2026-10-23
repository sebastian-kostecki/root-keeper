<?php

namespace App\Actions\Sources;

use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Enums\SourceKind;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Person;
use App\Models\Source;

class FillBurialPlacesFromGravestone
{
    /**
     * Give every deceased person listed on the gravestone whose burial place is empty the
     * gravestone's cemetery as burial place. A burial in the cemetery's locality that has no
     * cemetery yet gets one only when the person's gravestones have exactly one cemetery in that
     * locality (the same rule as the data migration). Other burial places, people without a death
     * and a gravestone without a cemetery are left alone.
     */
    public function handle(Source $gravestone): void
    {
        if ($gravestone->kind !== SourceKind::Gravestone || $gravestone->cemetery === null) {
            return;
        }

        $gravestone->buriedPeople()->lockForUpdate()->with('events')->get()->each(function (Person $person) use ($gravestone): void {
            if ($person->death() === null) {
                return;
            }

            $existing = $person->burial();

            if ($existing !== null) {
                $this->fillEmptyCemetery($person, $existing, $gravestone->cemetery);

                return;
            }

            $burial = $gravestone->tree->events()->create([
                'type' => EventType::Burial,
                'date_qualifier' => DateQualifier::Exact,
                'cemetery_id' => $gravestone->cemetery->id,
                'locality_id' => $gravestone->cemetery->locality_id,
            ]);

            $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        });
    }

    /**
     * Sets the cemetery of a burial that has none when it lies in the gravestone cemetery's locality and the
     * person's gravestones name exactly one cemetery there.
     */
    private function fillEmptyCemetery(Person $person, Event $burial, Cemetery $cemetery): void
    {
        if ($burial->cemetery_id !== null || $burial->locality_id !== $cemetery->locality_id) {
            return;
        }

        $cemeteryIds = $person->gravestones()
            ->reorder()
            ->join('cemeteries', 'cemeteries.id', '=', 'sources.cemetery_id')
            ->where('cemeteries.locality_id', $burial->locality_id)
            ->distinct()
            ->pluck('sources.cemetery_id');

        if ($cemeteryIds->count() === 1) {
            $burial->update(['cemetery_id' => $cemeteryIds->first()]);
        }
    }
}
