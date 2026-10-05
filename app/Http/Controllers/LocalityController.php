<?php

namespace App\Http\Controllers;

use App\Actions\Localities\StoreLocality;
use App\Actions\Localities\UpdateLocality;
use App\Http\Requests\LocalityStoreRequest;
use App\Http\Requests\LocalityUpdateRequest;
use App\Http\Resources\LocalityResource;
use App\Models\Locality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LocalityController extends Controller
{
    /**
     * Display the tree's gazetteer, with the side panel named by `?locality=` resolved lazily.
     */
    public function index(Request $request): Response
    {
        $tree = $request->user()->tree;

        return Inertia::render('localities/index', [
            'localities' => fn (): array => LocalityResource::collection(
                $tree->localities()->orderBy('name')->get()
            )->resolve(),
            'filters' => ['q' => $this->normalizeSearchQuery($request->query('q'))],
            'localityPanel' => function () use ($request): ?array {
                $key = $request->query('locality');

                if ($key === 'new') {
                    return ['locality' => null];
                }

                if (! is_string($key) || ! ctype_digit($key)) {
                    return null;
                }

                $locality = $request->user()->tree->localities()->findOrFail((int) $key);

                Gate::authorize('view', $locality);

                return ['locality' => LocalityResource::make($locality)->resolve()];
            },
        ]);
    }

    /**
     * Normalize the raw `q` query parameter into a trimmed, length-capped search string.
     */
    private function normalizeSearchQuery(mixed $q): string
    {
        if (! is_string($q)) {
            return '';
        }

        return rtrim(mb_substr(trim($q), 0, 100));
    }

    /**
     * Store a newly created locality in the tree and open its panel.
     */
    public function store(LocalityStoreRequest $request, StoreLocality $storeLocality): RedirectResponse
    {
        $locality = $storeLocality->handle(
            $request->user()->tree,
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dodano miejscowość')]);

        return to_route('localities.index', ['locality' => $locality->id]);
    }

    /**
     * Update the name and description of the given locality.
     */
    public function update(LocalityUpdateRequest $request, Locality $locality, UpdateLocality $updateLocality): RedirectResponse
    {
        $updateLocality->handle(
            $locality,
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Zapisano miejscowość')]);

        return back();
    }
}
