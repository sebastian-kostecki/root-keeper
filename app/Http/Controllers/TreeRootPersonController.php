<?php

namespace App\Http\Controllers;

use App\Actions\Trees\SetTreeRootPerson;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TreeRootPersonController extends Controller
{
    /**
     * Set the given person as the proband of the user's tree.
     */
    public function update(Request $request, Person $person, SetTreeRootPerson $setTreeRootPerson): RedirectResponse
    {
        Gate::authorize('update', $person);

        $setTreeRootPerson->handle($request->user()->tree, $person);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustawiono probanta')]);

        return back();
    }
}
