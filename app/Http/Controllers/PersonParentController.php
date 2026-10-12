<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateParentForPerson;
use App\Http\Requests\PersonParentStoreRequest;
use App\Models\Person;
use App\Support\PersonPanelRedirect;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PersonParentController extends Controller
{
    /**
     * Create a new person and link them as a parent of the given child, then open
     * the new parent's panel on the referring tree.
     */
    public function store(PersonParentStoreRequest $request, Person $person, CreateParentForPerson $createParentForPerson): RedirectResponse
    {
        $parent = $createParentForPerson->handle(
            $request->user()->tree,
            $person,
            $request->safe()->except('role'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano rodzica')]);

        return PersonPanelRedirect::to($request, $parent, route('tree.show', $person));
    }
}
