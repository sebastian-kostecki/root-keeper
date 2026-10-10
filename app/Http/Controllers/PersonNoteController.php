<?php

namespace App\Http\Controllers;

use App\Actions\Persons\UpdatePersonNote;
use App\Http\Requests\PersonNoteUpdateRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PersonNoteController extends Controller
{
    /**
     * Save the free-text note of the given person.
     */
    public function update(PersonNoteUpdateRequest $request, Person $person, UpdatePersonNote $updatePersonNote): RedirectResponse
    {
        $updatePersonNote->handle($person, $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano notatkę')]);

        return back();
    }
}
