<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateParentChildRelation;
use App\Http\Requests\ParentChildRelationStoreRequest;
use App\Models\ParentChildRelation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ParentChildRelationController extends Controller
{
    /**
     * Link a parent and a child within the user's own tree.
     */
    public function store(ParentChildRelationStoreRequest $request, CreateParentChildRelation $createParentChildRelation): RedirectResponse
    {
        $tree = $request->user()->tree;

        $parent = $tree->people()->findOrFail($request->safe()->integer('parent_id'));
        $child = $tree->people()->findOrFail($request->safe()->integer('child_id'));

        $createParentChildRelation->handle($tree, $parent, $child);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano relację')]);

        return back();
    }

    /**
     * Remove the given parent-child relation.
     */
    public function destroy(ParentChildRelation $parentChildRelation): RedirectResponse
    {
        Gate::authorize('delete', $parentChildRelation);

        $parentChildRelation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto relację')]);

        return back();
    }
}
