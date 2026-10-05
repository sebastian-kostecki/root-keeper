<?php

namespace App\Http\Controllers;

use App\Actions\Parishes\StoreParish;
use App\Actions\Parishes\UpdateParish;
use App\Enums\Religion;
use App\Http\Requests\ParishStoreRequest;
use App\Http\Requests\ParishUpdateRequest;
use App\Http\Resources\ParishResource;
use App\Models\Parish;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ParishController extends Controller
{
    /**
     * Display the tree's parish register, with the side panel named by `?parish=` resolved lazily.
     */
    public function index(Request $request): Response
    {
        $tree = $request->user()->tree;

        return Inertia::render('parishes/index', [
            'parishes' => fn (): array => ParishResource::collection(
                $tree->parishes()->with('locality')->orderBy('name')->get()
            )->resolve(),
            'filters' => ['q' => $this->normalizeSearchQuery($request->query('q'))],
            'religions' => Religion::options(),
            'localities' => fn (): array => $tree->localities()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($locality): array => ['id' => $locality->id, 'name' => $locality->name])
                ->all(),
            'parishPanel' => function () use ($request): ?array {
                $key = $request->query('parish');

                if ($key === 'new') {
                    return ['parish' => null];
                }

                if (! is_string($key) || ! ctype_digit($key)) {
                    return null;
                }

                $parish = $request->user()->tree->parishes()->with('locality')->findOrFail((int) $key);

                Gate::authorize('view', $parish);

                return ['parish' => ParishResource::make($parish)->resolve()];
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

        return to_route('parishes.index', ['parish' => $parish->id]);
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
}
