<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateMarriage;
use App\Http\Requests\PersonSpouseAttachRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PersonSpouseController extends Controller
{
    /**
     * Marry the given person to an existing person of the tree, with no date
     * and no place, and show the tree on the new union.
     */
    public function attach(PersonSpouseAttachRequest $request, Person $person, CreateMarriage $createMarriage): RedirectResponse
    {
        Gate::authorize('view', $person);

        $tree = $request->user()->tree;
        $spouse = $tree->people()->findOrFail($request->integer('spouse_id'));

        $createMarriage->handle($tree, $person, $spouse, []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano małżonka')]);

        return to_route('tree.show', ['person' => $person, 'spouse' => $spouse->id]);
    }
}
