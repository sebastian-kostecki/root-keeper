<?php

namespace App\Http\Controllers;

use App\Actions\Localities\MergeLocality;
use App\Http\Requests\LocalityMergeRequest;
use App\Models\Locality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LocalityMergeController extends Controller
{
    /**
     * Merge the given locality into another locality of the same tree, which then stays open in its panel.
     */
    public function __invoke(LocalityMergeRequest $request, Locality $locality, MergeLocality $mergeLocality): RedirectResponse
    {
        Gate::authorize('delete', $locality);

        $target = $request->user()->tree->localities()->findOrFail($request->integer('target_id'));

        $mergeLocality->handle($locality, $target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scalono z miejscowością „:name”', ['name' => $target->name])]);

        return to_route('places.index', ['locality' => $target->id]);
    }
}
