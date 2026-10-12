<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateSpouseForPerson;
use App\Http\Requests\PersonSpouseAttachRequest;
use App\Http\Requests\PersonSpouseStoreRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PersonSpouseController extends Controller
{
    /**
     * Marry the given person to an existing person of the tree, with no date
     * and no place, and show the tree on the new union.
     */
    public function attach(PersonSpouseAttachRequest $request, Person $person, CreateMarriage $createMarriage): RedirectResponse
    {
        $tree = $request->user()->tree;
        $spouse = $tree->people()->findOrFail($request->integer('spouse_id'));

        $createMarriage->handle($tree, $person, $spouse, []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano małżonka')]);

        return $this->showNewUnion($person, $spouse);
    }

    /**
     * Create a new person, marry them to the given person with no date and no
     * place, and show the tree on the new union.
     */
    public function store(PersonSpouseStoreRequest $request, Person $person, CreateSpouseForPerson $createSpouseForPerson): RedirectResponse
    {
        $spouse = $createSpouseForPerson->handle($request->user()->tree, $person, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano małżonka')]);

        return $this->showNewUnion($person, $spouse);
    }

    /**
     * Redirect to the tree centred on the given person, on the union with the new
     * spouse, with the spouse's panel open. Does not read the Referer.
     */
    private function showNewUnion(Person $person, Person $spouse): RedirectResponse
    {
        return redirect()->to(route('tree.show', $person).'?'.http_build_query([
            'spouse' => $spouse->id,
            'person' => $spouse->id,
        ]));
    }
}
