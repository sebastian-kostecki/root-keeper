<?php

namespace App\Http\Controllers;

use App\Actions\Localities\DeleteLocality;
use App\Actions\Localities\StoreLocality;
use App\Actions\Localities\UpdateLocality;
use App\Http\Requests\LocalityStoreRequest;
use App\Http\Requests\LocalityUpdateRequest;
use App\Models\Locality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LocalityController extends Controller
{
    /**
     * Redirect the old gazetteer address to the places page, keeping the search and the panel key.
     */
    public function index(Request $request): RedirectResponse
    {
        return to_route('places.index', array_filter(
            $request->only(['locality', 'q']),
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
    }

    /**
     * Store a newly created locality in the tree and open its panel.
     */
    public function store(LocalityStoreRequest $request, StoreLocality $storeLocality): RedirectResponse
    {
        $locality = $storeLocality->handle(
            $request->user()->tree,
            $request->localityDetails(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano miejscowość')]);

        return to_route('places.index', ['locality' => $locality->id]);
    }

    /**
     * Update the details of the given locality.
     */
    public function update(LocalityUpdateRequest $request, Locality $locality, UpdateLocality $updateLocality): RedirectResponse
    {
        $updateLocality->handle(
            $locality,
            $request->localityDetails(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano miejscowość')]);

        return back();
    }

    /**
     * Delete the given locality, which must be unused.
     */
    public function destroy(Locality $locality, DeleteLocality $deleteLocality): RedirectResponse
    {
        Gate::authorize('delete', $locality);

        $deleteLocality->handle($locality);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto miejscowość')]);

        return to_route('places.index');
    }
}
