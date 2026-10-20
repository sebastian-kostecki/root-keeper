<?php

namespace App\Http\Controllers;

use App\Actions\Cemeteries\DeleteCemetery;
use App\Actions\Cemeteries\StoreCemetery;
use App\Actions\Cemeteries\UpdateCemetery;
use App\Http\Requests\CemeteryStoreRequest;
use App\Http\Requests\CemeteryUpdateRequest;
use App\Models\Cemetery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CemeteryController extends Controller
{
    /**
     * Store a newly created cemetery in the tree and open its panel.
     */
    public function store(CemeteryStoreRequest $request, StoreCemetery $storeCemetery): RedirectResponse
    {
        $cemetery = $storeCemetery->handle($request->user()->tree, $request->cemeteryDetails());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano cmentarz.')]);

        return to_route('places.index', ['kind' => 'cmentarze', 'cemetery' => $cemetery->id]);
    }

    /**
     * Update the given cemetery.
     */
    public function update(CemeteryUpdateRequest $request, Cemetery $cemetery, UpdateCemetery $updateCemetery): RedirectResponse
    {
        $updateCemetery->handle($cemetery, $request->cemeteryDetails());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano cmentarz.')]);

        return back();
    }

    /**
     * Delete the given cemetery, which must be unused.
     */
    public function destroy(Cemetery $cemetery, DeleteCemetery $deleteCemetery): RedirectResponse
    {
        Gate::authorize('delete', $cemetery);

        $deleteCemetery->handle($cemetery);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto cmentarz.')]);

        return to_route('places.index', ['kind' => 'cmentarze']);
    }
}
