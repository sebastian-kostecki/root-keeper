<?php

namespace App\Http\Controllers;

use App\Actions\Parishes\MergeParish;
use App\Http\Requests\ParishMergeRequest;
use App\Models\Parish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ParishMergeController extends Controller
{
    /**
     * Merge the given parish into another parish of the same tree, which then stays open in its panel.
     */
    public function __invoke(ParishMergeRequest $request, Parish $parish, MergeParish $mergeParish): RedirectResponse
    {
        Gate::authorize('delete', $parish);

        $target = $request->user()->tree->parishes()->findOrFail($request->integer('target_id'));

        $mergeParish->handle($parish, $target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scalono z parafią „:name”', ['name' => $target->label()])]);

        return to_route('places.index', ['kind' => 'parafie', 'parish' => $target->id]);
    }
}
