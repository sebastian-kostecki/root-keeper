<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateParentForPerson;
use App\Http\Requests\PersonParentStoreRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PersonParentController extends Controller
{
    /**
     * Create a new person and link them as a parent of the given child.
     */
    public function store(PersonParentStoreRequest $request, Person $person, CreateParentForPerson $createParentForPerson): RedirectResponse
    {
        Gate::authorize('view', $person);

        $createParentForPerson->handle(
            $request->user()->tree,
            $person,
            $request->safe()->except('role'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano rodzica')]);

        return back();
    }
}
