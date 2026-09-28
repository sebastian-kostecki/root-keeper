<?php

namespace App\Http\Controllers;

use App\Actions\Relations\AttachChildToParents;
use App\Http\Requests\PersonChildAttachRequest;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PersonChildController extends Controller
{
    /**
     * Make an existing person the child of the given person and, when given,
     * their partner.
     */
    public function attach(PersonChildAttachRequest $request, Person $person, AttachChildToParents $attachChildToParents): RedirectResponse
    {
        Gate::authorize('view', $person);

        $tree = $request->user()->tree;
        $child = $tree->people()->findOrFail($request->integer('child_id'));
        $otherParent = $request->filled('other_parent_id')
            ? $tree->people()->findOrFail($request->integer('other_parent_id'))
            : null;

        $attachChildToParents->handle($tree, $child, $otherParent === null ? [$person] : [$person, $otherParent]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano dziecko')]);

        return back();
    }
}
