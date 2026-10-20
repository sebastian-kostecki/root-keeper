<?php

namespace App\Http\Controllers;

use App\Actions\Cemeteries\MergeCemetery;
use App\Http\Requests\CemeteryMergeRequest;
use App\Models\Cemetery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CemeteryMergeController extends Controller
{
    /**
     * Merge the given cemetery into another cemetery of the same tree, which then stays open in its panel.
     */
    public function __invoke(CemeteryMergeRequest $request, Cemetery $cemetery, MergeCemetery $mergeCemetery): RedirectResponse
    {
        Gate::authorize('delete', $cemetery);

        $target = $request->user()->tree->cemeteries()->with('locality')->findOrFail($request->integer('target_id'));

        $mergeCemetery->handle($cemetery, $target);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scalono z cmentarzem „:name”', ['name' => $target->label()])]);

        return to_route('places.index', ['kind' => 'cmentarze', 'cemetery' => $target->id]);
    }
}
