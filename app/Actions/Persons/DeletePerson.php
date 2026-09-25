<?php

namespace App\Actions\Persons;

use App\Models\Person;
use Illuminate\Support\Facades\DB;

class DeletePerson
{
    /**
     * Delete a person, removing every event whose only participant is this
     * person before deleting the person itself. Events shared with other
     * participants keep their remaining participant rows. Scans are never
     * touched; only the event_scan rows of removed events cascade away.
     */
    public function handle(Person $person): void
    {
        DB::transaction(function () use ($person): void {
            $person->events()
                ->has('participants', '=', 1)
                ->get()
                ->each(fn ($event) => $event->delete());

            $person->delete();
        });
    }
}
