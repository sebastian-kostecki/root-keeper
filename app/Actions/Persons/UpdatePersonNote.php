<?php

namespace App\Actions\Persons;

use App\Models\Person;

class UpdatePersonNote
{
    /**
     * Replace the free-text note of a person. Touches no other column.
     */
    public function handle(Person $person, ?string $note): Person
    {
        $person->update(['note' => $note]);

        return $person;
    }
}
