<?php

namespace App\Http\Controllers;

use App\Actions\Parishes\DeleteParish;
use App\Actions\Parishes\StoreParish;
use App\Actions\Parishes\UpdateParish;
use App\Enums\Religion;
use App\Http\Requests\ParishStoreRequest;
use App\Http\Requests\ParishUpdateRequest;
use App\Models\Parish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ParishController extends Controller
{
    /**
     * Redirect the old parish register address to the places page on the parish tab, keeping the search and the panel key.
     */
    public function index(Request $request): RedirectResponse
    {
        return to_route('places.index', ['kind' => 'parafie', ...array_filter(
            $request->only(['parish', 'q']),
            fn (mixed $value): bool => is_string($value) && $value !== '',
        )]);
    }

    /**
     * Store a newly created parish in the tree and open its panel.
     */
    public function store(ParishStoreRequest $request, StoreParish $storeParish): RedirectResponse
    {
        $parish = $storeParish->handle(
            $request->user()->tree,
            $request->string('name')->toString(),
            $request->enum('religion', Religion::class),
            $request->filled('locality_id') ? $request->integer('locality_id') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano parafię')]);

        return to_route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]);
    }

    /**
     * Update the name, religion and seat of the given parish.
     */
    public function update(ParishUpdateRequest $request, Parish $parish, UpdateParish $updateParish): RedirectResponse
    {
        $updateParish->handle(
            $parish,
            $request->string('name')->toString(),
            $request->enum('religion', Religion::class),
            $request->filled('locality_id') ? $request->integer('locality_id') : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano parafię')]);

        return back();
    }

    /**
     * Delete the given parish, which must be unused.
     */
    public function destroy(Parish $parish, DeleteParish $deleteParish): RedirectResponse
    {
        Gate::authorize('delete', $parish);

        $deleteParish->handle($parish);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto parafię')]);

        return to_route('places.index', ['kind' => 'parafie']);
    }
}
