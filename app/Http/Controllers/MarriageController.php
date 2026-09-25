<?php

namespace App\Http\Controllers;

use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\UpdateMarriage;
use App\Enums\EventType;
use App\Http\Requests\MarriageStoreRequest;
use App\Http\Requests\MarriageUpdateRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MarriageController extends Controller
{
    /**
     * Create a marriage between the two given people in the user's own tree.
     */
    public function store(MarriageStoreRequest $request, CreateMarriage $createMarriage): RedirectResponse
    {
        $tree = $request->user()->tree;

        $person = $tree->people()->findOrFail($request->validated('person_id'));
        $spouse = $tree->people()->findOrFail($request->validated('spouse_id'));

        $createMarriage->handle($tree, $person, $spouse, $request->validated('marriage', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano małżeństwo')]);

        return back();
    }

    /**
     * Update the given marriage's date and place.
     */
    public function update(MarriageUpdateRequest $request, Event $marriage, UpdateMarriage $updateMarriage): RedirectResponse
    {
        $updateMarriage->handle($marriage, $request->validated('marriage', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano zmiany')]);

        return back();
    }

    /**
     * Remove the given marriage.
     */
    public function destroy(Event $marriage): RedirectResponse
    {
        Gate::authorize('update', $marriage);
        abort_unless($marriage->type === EventType::Marriage, 404);

        $marriage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto małżeństwo')]);

        return back();
    }
}
