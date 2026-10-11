<?php

namespace App\Http\Controllers;

use App\Actions\Trees\CreateProband;
use App\Actions\Trees\SetTreeRootPerson;
use App\Http\Requests\TreeProbandAttachRequest;
use App\Http\Requests\TreeProbandStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TreeProbandController extends Controller
{
    /**
     * Make an existing person of the user's tree the proband.
     */
    public function attach(TreeProbandAttachRequest $request, SetTreeRootPerson $setTreeRootPerson): RedirectResponse
    {
        $tree = $request->user()->tree;
        $person = $tree->people()->findOrFail($request->integer('person_id'));

        Gate::authorize('update', $person);

        $setTreeRootPerson->handle($tree, $person);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustawiono probanta')]);

        return redirect()->to(route('tree.index').'?person='.$person->id);
    }

    /**
     * Create a new person and make them the proband of the user's tree.
     */
    public function store(TreeProbandStoreRequest $request, CreateProband $createProband): RedirectResponse
    {
        $person = $createProband->handle($request->user()->tree, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano osobę i ustawiono probanta')]);

        return redirect()->to(route('tree.index').'?person='.$person->id);
    }
}
